// Карточка букета = src/app/admin/(panel)/bouquets/[id]/BouquetEditor.tsx
// Фото, поводы, цвета, формат, состав по размерам XS–XXL, упаковка у каждого размера, коллекция.
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var on_ = U.attr;
  var data = U.json("admin-data");
  var stamp = data.stamp; // отпечаток данных: сервер не даст молча затереть правки с другого устройства
  var flowers = data.flowers;
  var collections = data.collections;
  var siblings = data.siblings;
  var colors = data.colors;
  var FORMATS = data.formats;
  var SECTIONS = data.sections;
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || "";

  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }
  var initial = clone(data.initial);
  if (!initial.sections) initial.sections = [];
  if (initial.weddingOnly === undefined) initial.weddingOnly = true;
  if (!initial.onlyShop) initial.onlyShop = ""; // «Только из Уручья»: код филиала, "" — из любого
  var shops = data.shops || [];
  function shopLabel(code) {
    return (shops.filter(function (x) { return x.code === code; })[0] || {}).label || "";
  }
  if (!initial.swaps || Array.isArray(initial.swaps)) initial.swaps = {}; // замены в этом букете: {цветок: [замена, …]}
  if (!initial.photoFocus || Array.isArray(initial.photoFocus)) initial.photoFocus = {}; // кадр фото: {путь: [x, y]} в %
  // упаковка у каждого размера своя; текст поля храним отдельно, чтобы можно было стереть и набрать
  function packagingTexts(x) {
    var t = {};
    x.sizes.forEach(function (s) { t[s.code] = U.kopToInput(s.packagingKop); });
    return t;
  }
  var state = {
    b: clone(initial),
    sizeCode: initial.photoSize,
    packagingText: packagingTexts(initial),
    qtyText: {}, // пока набирают количество («1,»), держим текст
    msg: null,
    pending: false,
    uploading: false,
    framing: null, // фото, у которого сейчас двигаем кадр
    flowerQuery: "",
    timerText: String(data.timerHours), // часы таймера «собран сегодня» в поле
  };
  // «+ Собран сегодня» в списке: сразу «Букет дня», на сайте и таймер — применятся кнопкой «Сохранить»
  if (data.today) Object.assign(state.b, { isDayBouquet: true, enabled: true, timer: data.timerHours });

  // Время сервера (Минск) — чтобы «скроется через …» не зависело от часов и пояса компьютера
  var serverNow = data.now, loadedAt = Date.now();
  function parseTime(t) {
    var m = String(t).match(/^(\d+)-(\d+)-(\d+) (\d+):(\d+)/);
    return m ? Date.UTC(+m[1], m[2] - 1, +m[3], +m[4], +m[5]) : NaN;
  }
  function nowMs() {
    return parseTime(serverNow) + (Date.now() - loadedAt);
  }
  function timerDate(ms) {
    var d = new Date(ms), p = function (n) { return (n < 10 ? "0" : "") + n; };
    return p(d.getUTCDate()) + "." + p(d.getUTCMonth() + 1) + " в " + p(d.getUTCHours()) + ":" + p(d.getUTCMinutes());
  }
  function timerLeft(ms) {
    var min = Math.max(1, Math.ceil((ms - nowMs()) / 60000)), d = Math.floor(min / 1440), h = Math.floor((min % 1440) / 60), m = min % 60;
    if (d) return d + " д" + (h ? " " + h + " ч" : "");
    if (h) return h + " ч" + (m ? " " + m + " мин" : "");
    return m + " мин";
  }
  var byId = {};
  flowers.forEach(function (f) { byId[f.id] = f; });

  function size() {
    return state.b.sizes.filter(function (s) { return s.code === state.sizeCode; })[0];
  }
  function flowersKop(s) {
    return s.items.reduce(function (sum, i) { return sum + Math.round(((byId[i.flowerId] || {}).priceKop || 0) * i.qty); }, 0);
  }
  // Замены задаются в этом букете для каждого цветка свои (решение владельца 2026-10-31)
  function swapsOf(flowerId) {
    return state.b.swaps[flowerId] || [];
  }
  // чем заменим цветок, которого нет: первая замена из этого букета в наличии
  function replacement(f) {
    if (!f || f.inStock) return undefined;
    return swapsOf(f.id).map(function (id) { return byId[id]; }).filter(function (x) { return x && x.inStock; })[0];
  }
  // Цветок только в одном филиале (справочник «Цветы») и в этом букете нет замены, которая есть везде, —
  // букет можно заказать только из этого филиала (как Pricing::itemShops). Код филиала или "".
  function flowerOnly(f) {
    if (!f) return "";
    var opts = [];
    if (f.inStock) opts.push(f.onlyShop || "");
    swapsOf(f.id).forEach(function (id) { var x = byId[id]; if (x && x.inStock) opts.push(x.onlyShop || ""); });
    if (!opts.length || opts.indexOf("") >= 0) return "";
    return opts[0];
  }
  function setSwaps(flowerId, list) {
    var w = Object.assign({}, state.b.swaps);
    if (list.length) w[flowerId] = list;
    else delete w[flowerId];
    set({ swaps: w });
  }
  // все цветы букета (из всех размеров), по порядку появления
  function bouquetFlowers() {
    var seen = {}, out = [];
    state.b.sizes.forEach(function (x) {
      x.items.forEach(function (i) { if (!seen[i.flowerId] && byId[i.flowerId]) { seen[i.flowerId] = true; out.push(byId[i.flowerId]); } });
    });
    return out;
  }
  function priceOf(s) {
    return s.items.length ? U.roundPriceKop(flowersKop(s) + s.packagingKop) : null;
  }
  function missing(s) {
    return s.items
      .filter(function (i) { var f = byId[i.flowerId]; return !(f && f.inStock) && !replacement(f); })
      .map(function (i) { return (byId[i.flowerId] || {}).name; });
  }
  function norm(t) {
    return t.toLowerCase().replace(/ё/g, "е").trim();
  }

  function set(p) {
    Object.assign(state.b, p);
    ui.render();
  }
  function patchSize(fn) {
    state.b.sizes = state.b.sizes.map(function (s) { return s.code === state.sizeCode ? fn(s) : s; });
    ui.render();
  }
  function setQty(flowerId, qty) {
    patchSize(function (s) {
      return Object.assign({}, s, { items: s.items.map(function (i) { return i.flowerId === flowerId ? { flowerId: i.flowerId, qty: Math.max(0.5, qty) } : i; }) });
    });
  }
  function addFlower(flowerId) {
    state.flowerQuery = "";
    patchSize(function (s) { return Object.assign({}, s, { items: s.items.concat([{ flowerId: flowerId, qty: 1 }]) }); });
  }
  function toggleIn(list, v) {
    return list.indexOf(v) >= 0 ? list.filter(function (x) { return x !== v; }) : list.concat([v]);
  }

  // ---------- Кадр фото ----------
  // На сайте фото обрезаются под рамку (карточка каталога, «Букет дня», корзина). Нажали на букет на фото —
  // запоминаем эту точку (в % от левого и верхнего края), и на сайте фото обрезается вокруг неё (CSS object-position).
  function focusOf(p) {
    return state.b.photoFocus[p] || [50, 50];
  }
  // object-position для рамки bw×bh: точно как на сайте (focus.js), когда уже известен размер фото
  var dims = {};
  function posCss(p, bw, bh) {
    var f = focusOf(p), d = dims[p];
    if (!d) {
      dims[p] = "loading";
      var im = new Image();
      im.onload = function () { dims[p] = [im.naturalWidth, im.naturalHeight]; ui.render(); };
      im.src = p;
    }
    return d && d !== "loading" && window.ArtfloraFocus ? window.ArtfloraFocus.calc(f[0], f[1], d[0], d[1], bw, bh) : f[0] + "% " + f[1] + "%";
  }
  function setFocus(p, xy) {
    var m = Object.assign({}, state.b.photoFocus);
    if (xy[0] === 50 && xy[1] === 50) delete m[p];
    else m[p] = xy;
    set({ photoFocus: m });
  }
  // Рамки, как на сайте (ширина × высота): каталог на компьютере 295×340, на телефоне 165×210, «Букет дня» 3:4, корзина и страница букета — почти квадрат
  var FRAMES = [
    { name: "Каталог, компьютер", w: 118, h: 136 },
    { name: "Каталог, телефон", w: 99, h: 126 },
    { name: "Главная: букет дня", w: 102, h: 136 },
    { name: "Страница букета, корзина", w: 136, h: 136 },
  ];
  function framingHtml(on) {
    var p = state.framing;
    if (!p || state.b.photos.indexOf(p) < 0) return "";
    var f = focusOf(p);
    var h = '<div class="card stack framing" style="gap: 12px">';
    h += '<div class="row between"><b>Кадр фото на сайте</b><button type="button" class="btn btn-outline"' + on("click", function () { state.framing = null; ui.render(); }) + ">Готово</button></div>";
    h += '<p class="small muted" style="margin: 0">Нажмите на фото туда, где букет (или его центр) — на сайте фото обрежется вокруг этой точки. Справа видно, как оно будет выглядеть. Не забудьте «Сохранить».</p>';
    h += '<div class="framing-body">';
    h += '<div class="framing-pick"' + on("click", function (e, el) {
      var img = el.querySelector("img");
      var r = img.getBoundingClientRect();
      if (!r.width || !r.height) return;
      var x = Math.round(Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100)));
      var y = Math.round(Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100)));
      setFocus(p, [x, y]);
    }) + '><span class="framing-img"><img src="' + esc(p) + '" alt="" draggable="false"><i class="framing-dot" style="left: ' + f[0] + "%; top: " + f[1] + '%"></i></span></div>';
    h += '<div class="framing-previews">';
    FRAMES.forEach(function (fr) {
      h += '<figure><span style="width: ' + fr.w + "px; height: " + fr.h + 'px"><img src="' + esc(p) + '" alt="" style="object-position: ' + posCss(p, fr.w, fr.h) + '"></span><figcaption>' + esc(fr.name) + "</figcaption></figure>";
    });
    h += "</div></div>";
    h += '<div class="row" style="gap: 8px"><button type="button" class="btn btn-outline"' + on("click", function () { setFocus(p, [50, 50]); }) + ">По центру</button>" +
      '<span class="small muted">' + (f[0] === 50 && f[1] === 50 ? "Сейчас: по центру, как было" : "Точка: " + f[0] + "% слева, " + f[1] + "% сверху") + "</span></div>";
    return h + "</div>";
  }

  function onUpload(files) {
    if (!files || !files.length) return;
    state.uploading = true;
    ui.render();
    var added = [];
    var chain = Promise.resolve();
    Array.prototype.forEach.call(files, function (file) {
      chain = chain.then(function () {
        return window.ArtfloraAdmin.upload("/admin/upload", file).then(
          function (r) {
            if (r.ok && r.path) added.push(r.path);
            else state.msg = { ok: false, text: r.error || "Не удалось загрузить фото" };
          },
          function () { state.msg = { ok: false, text: "Не удалось загрузить фото" }; },
        );
      });
    });
    chain.then(function () {
      state.b.photos = state.b.photos.concat(added);
      state.uploading = false;
      ui.render();
    });
  }

  function save() {
    var bad = state.b.sizes.filter(function (s) { return U.parseByn(state.packagingText[s.code]) === null; })[0];
    if (bad) {
      state.sizeCode = bad.code;
      state.msg = { ok: false, text: "Проверьте стоимость упаковки в размере " + bad.code };
      return ui.render();
    }
    state.pending = true;
    ui.render();
    U.post("/admin/bouquets/save", Object.assign({}, state.b, { stamp: stamp })).then(function (r) {
      state.pending = false;
      state.msg = r.ok ? { ok: true, text: "Сохранено, сайт обновлён" } : { ok: false, text: r.error || "Ошибка" };
      if (r.ok) {
        stamp = r.stamp;
        Object.assign(state.b, { hideAt: r.hideAt, hiddenByTimerAt: r.hiddenByTimerAt, timer: "keep" });
        serverNow = r.now;
        loadedAt = Date.now();
        initial = clone(state.b);
        document.title = (state.b.name || "Без названия") + " — Управление сайтом";
      }
      ui.render();
    });
  }

  // Действия, после которых открывается другая страница: обычная форма
  function submit(action, fields) {
    var f = document.createElement("form");
    f.method = "post";
    f.action = action;
    fields._csrf = csrf;
    Object.keys(fields).forEach(function (k) {
      var i = document.createElement("input");
      i.type = "hidden";
      i.name = k;
      i.value = fields[k];
      f.appendChild(i);
    });
    document.body.appendChild(f);
    state.pending = true;
    ui.render();
    f.submit();
  }

  function view(on) {
    var b = state.b;
    var s = size();
    var code = state.sizeCode;
    var sum = flowersKop(s);
    var used = {};
    s.items.forEach(function (i) { used[i.flowerId] = true; });
    var candidates = flowers.filter(function (f) { return !used[f.id] && norm(f.name).indexOf(norm(state.flowerQuery)) >= 0; });
    var h = "";

    // Шапка
    h += '<div class="row between"><div class="stack" style="gap: 6px"><a href="/admin/bouquets" class="small">← Все букеты</a><h1>' + esc(b.name || "Без названия") + "</h1></div>";
    h += '<div class="row"><span class="row small" style="gap: 8px"><button type="button" class="switch ' + (b.enabled ? "on" : "") + '" aria-label="Показывать на сайте"' +
      on("click", function () { set({ enabled: !b.enabled }); }) + "></button>" + (b.enabled ? "На сайте" : b.hiddenByTimerAt ? "Скрыт по таймеру" : "Выключен") + "</span>";
    h += '<button type="button" class="btn btn-outline"' + on("click", function () {
      state.b = clone(initial);
      state.packagingText = packagingTexts(initial);
      state.qtyText = {};
      state.msg = null;
      ui.render();
    }) + ">Отменить</button>";
    h += '<button type="button" class="btn"' + on_("disabled", state.pending) + on("click", save) + ">" + (state.pending ? "Сохраняем…" : "Сохранить") + "</button></div></div>";
    h += U.msgHtml(state.msg, "align-self: flex-start");

    h += '<div class="admin-grid"><div class="stack" style="gap: 20px">';

    // Фото
    h += '<div class="photo-row">';
    b.photos.forEach(function (p, i) {
      h += '<div class="ph' + (state.framing === p ? " framing-on" : "") + '"' + (i === 0 ? ' style="outline: 2px solid var(--cherry)"' : "") + '><img src="' + esc(p) + '" alt="" title="Нажмите, чтобы подвинуть кадр" style="cursor: pointer; object-position: ' + posCss(p, 120, 120) + '"' +
        on("click", function () { state.framing = state.framing === p ? null : p; ui.render(); }) + ">";
      h += '<button type="button" class="ph-frame"' + on("click", function () { state.framing = state.framing === p ? null : p; ui.render(); }) + ">✥ кадр</button>";
      h += '<button type="button" aria-label="Убрать фото"' + on("click", function () { set({ photos: b.photos.filter(function (_, n) { return n !== i; }) }); }) + ">×</button>";
      if (i > 0) {
        h += '<button type="button" style="left: 4px; right: auto; width: auto; border-radius: 999px; padding: 0 8px; font-size: 12px"' +
          on("click", function () { set({ photos: [p].concat(b.photos.filter(function (_, n) { return n !== i; })) }); }) + ">главное</button>";
      }
      h += "</div>";
    });
    h += '<label class="ph" style="display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: 700">' + (state.uploading ? "Загрузка…" : "+ Фото") +
      '<input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple hidden' + on("change", function (e) { onUpload(e.target.files); }) + "></label></div>";
    if (b.photos.length) h += '<p class="small muted" style="margin: -8px 0 0">Букет на сайте обрезан не так? Нажмите на фото (или «✥ кадр») и отметьте, где букет.</p>';
    h += framingHtml(on);

    // Поля
    h += '<label class="field"><span>Название</span><input class="input" data-k="name" value="' + esc(b.name) + '"' + on("input", function (e) { set({ name: e.target.value }); }) + "></label>";
    h += '<label class="field"><span>Адрес страницы: artflora.by/bouquet/…</span><input class="input" data-k="slug" value="' + esc(b.slug) + '"' +
      on("input", function (e) { set({ slug: e.target.value.toLowerCase() }); }) + "></label>";
    h += '<label class="field"><span>Описание</span><textarea class="input" rows="3" data-k="description" placeholder="Короткое описание для покупателя"' +
      on("input", function (e) { set({ description: e.target.value }); }) + ">" + esc(b.description) + "</textarea></label>";

    h += '<div class="stack" style="gap: 8px"><span class="label">Поводы</span><div class="row" style="gap: 6px">';
    data.occasions.forEach(function (o) {
      if (o.slug === "exotic") return; // «Экзотика» — в разделах каталога ниже
      h += '<button type="button" class="chip ' + (b.occasions.indexOf(o.slug) >= 0 ? "on" : "") + '"' +
        on("click", function () { set({ occasions: toggleIn(b.occasions, o.slug) }); }) + ">" + esc(o.label) + "</button>";
    });
    h += "</div>";
    // Свадебные — только в своём разделе (по умолчанию так): в общем каталоге, поиске и на главной их нет
    if (b.occasions.indexOf("wedding") >= 0) {
      h += '<label class="check"><input type="checkbox"' + on_("checked", b.weddingOnly) + on("change", function (e) { set({ weddingOnly: e.target.checked }); }) +
        "> Только в разделе «Свадебные букеты»</label>" +
        '<span class="small muted">В общем каталоге, поиске, других разделах и на главной его не будет. Ссылка на букет работает.</span>';
    }
    h += "</div>";

    // Где собираем (решение владельца 2026-11-15): весь букет только из одного филиала. Ещё так бывает само —
    // если в составе цветок, который есть только в одном филиале, и замены для него в этом букете нет.
    if (shops.length > 1) {
      var auto = [];
      bouquetFlowers().forEach(function (f) {
        var code = flowerOnly(f);
        if (code) auto.push(f.name.toLowerCase() + " — " + shopLabel(code).replace("Только", "только"));
      });
      h += '<div class="stack" style="gap: 8px"><span class="label">📍 Где можно заказать</span><div class="row" style="gap: 6px">';
      [{ code: "", label: "В любом филиале" }].concat(shops).forEach(function (x) {
        h += '<button type="button" class="chip ' + (b.onlyShop === x.code ? "on" : "") + '"' + on("click", function () { set({ onlyShop: x.code }); }) + ">" + esc(x.label) + "</button>";
      });
      h += "</div>";
      h += '<span class="small muted">«Только из …» — на сайте жирная пометка, самовывоз только из этого магазина, доставка считается от него (в дальний конец города дороже).</span>';
      if (auto.length && !b.onlyShop) h += '<span class="pill onlyshop" style="align-self: flex-start">📍 Сейчас и так: ' + esc(auto.join("; ")) + " — замены в букете нет</span>";
      h += "</div>";
    }

    // Разделы над каталогом: можно несколько. Некоторые включаются сами (формат, розы в составе) — их не выключить.
    var hasRoses = b.sizes.some(function (s) {
      return s.items.some(function (i) { var f = byId[i.flowerId]; return f && f.name.toLowerCase().indexOf("роза") >= 0; });
    });
    // Монобукет — один вид цветов во всех размерах, зелень и декор не считаются (как Catalog::isMono на сервере)
    var monoKinds = {};
    b.sizes.forEach(function (s) {
      s.items.forEach(function (i) {
        var f = byId[i.flowerId];
        if (f && f.kind === "FLOWER") monoKinds[f.name.trim().toLowerCase().split(/[\s,]+/)[0]] = true;
      });
    });
    var isMono = Object.keys(monoKinds).length === 1;
    h += '<div class="stack" style="gap: 8px"><span class="label">Разделы каталога <span class="small muted">(кнопки над каталогом, можно несколько)</span></span><div class="row" style="gap: 6px">';
    Object.keys(SECTIONS).forEach(function (k) {
      // «Монобукеты» по составу включаются сами, но их можно снять: тогда хранится nomono
      if (k === "mono") {
        var monoOn = isMono ? b.sections.indexOf("nomono") < 0 : b.sections.indexOf("mono") >= 0;
        h += '<button type="button" class="chip ' + (monoOn ? "on" : "") + '"' + (isMono ? ' title="Букет из одного вида цветов — попал сюда сам. Нажмите, чтобы убрать"' : "") +
          on("click", function () {
            var l = b.sections.filter(function (x) { return x !== "mono" && x !== "nomono"; });
            if (isMono) { if (monoOn) l.push("nomono"); } else if (!monoOn) l.push("mono");
            set({ sections: l });
          }) + ">" + esc(SECTIONS[k]) + "</button>";
        return;
      }
      var auto = k === "roses" ? hasRoses : k === b.format;
      var onNow = k === "exotic" ? b.occasions.indexOf("exotic") >= 0 : auto || b.sections.indexOf(k) >= 0;
      var why = k === "roses" ? "В составе есть роза, букет и так в «Розах»" : "Так выбран формат букета";
      h += '<button type="button" class="chip ' + (onNow ? "on" : "") + '"' + (auto ? ' title="' + why + '"' + on_("disabled", true) :
        on("click", function () {
          if (k === "exotic") set({ occasions: toggleIn(b.occasions, "exotic") });
          else set({ sections: toggleIn(b.sections, k) });
        })) + ">" + esc(SECTIONS[k]) + "</button>";
    });
    h += "</div>";
    h += '<span class="small muted">«Розы» включаются сами, если в составе есть роза; «Монобукеты» — если букет из одного вида цветов (зелень не считается), их можно снять; шляпная коробка, сумка и корзина — по полю «Формат» ниже.</span></div>';

    h += '<div class="stack" style="gap: 8px"><span class="label">Цвет букета <span class="small muted">(для фильтра в каталоге, можно несколько; свои цвета — в «Настройках»)</span></span><div class="row" style="gap: 6px">';
    colors.forEach(function (c) {
      h += '<button type="button" class="chip ' + (b.colors.indexOf(c.slug) >= 0 ? "on" : "") + '"' + on("click", function () { set({ colors: toggleIn(b.colors, c.slug) }); }) + ">" +
        '<i class="swatch" style="background: ' + esc(c.swatch) + '" aria-hidden="true"></i>' + esc(c.label) + "</button>";
    });
    h += "</div></div>";

    h += '<div class="row" style="gap: 12px"><label class="field" style="flex: 1 1 200px"><span>Формат</span><select class="input"' + on("change", function (e) { set({ format: e.target.value }); }) + ">";
    Object.keys(FORMATS).forEach(function (k) { h += '<option value="' + k + '"' + on_("selected", b.format === k) + ">" + esc(FORMATS[k].label) + "</option>"; });
    h += "</select></label>";
    h += '<label class="check" style="margin-top: 22px" title="Большой блок «Букет дня · собран сегодня» на главной, один на сайт"><input type="checkbox"' + on_("checked", b.isDayBouquet) + on("change", function (e) {
      var p = { isDayBouquet: e.target.checked };
      // «Букет дня» = собран сегодня: таймер ставим сразу (виден ниже, можно поменять или снять)
      if (e.target.checked && !b.hideAt && b.timer === "keep") p.timer = data.timerHours;
      if (!e.target.checked && !initial.isDayBouquet && b.timer !== "keep" && !initial.hideAt) p.timer = "keep";
      set(p);
    }) + "> Букет дня (на главной)</label>";
    h += '<label class="check" style="margin-top: 22px"><input type="checkbox"' + on_("checked", b.isHit) + on("change", function (e) { set({ isHit: e.target.checked }); }) + "> Хит недели</label>";
    h += '<label class="check" style="margin-top: 22px"><input type="checkbox"' + on_("checked", b.isExclusive) + on("change", function (e) { set({ isExclusive: e.target.checked }); }) + "> Эксклюзив недели</label>";
    h += '<label class="check" style="margin-top: 22px" title="Всегда в начале каталога: сразу после «Букета дня», выше хитов и новых букетов"><input type="checkbox"' + on_("checked", b.isPinned) + on("change", function (e) { set({ isPinned: e.target.checked }); }) + "> ⭐ Избранный (всегда в начале)</label></div>";

    h += timerView(on);

    // Состав
    h += '<div class="card stack" style="gap: 14px"><div class="row between"><span class="label" style="font-size: 18px">Состав</span><div class="row" style="gap: 6px"><span class="small muted">Размер:</span><div class="seg">';
    b.sizes.forEach(function (x) {
      var p = priceOf(x);
      h += '<button type="button" class="' + (x.code === code ? "on" : "") + '"' + on("click", function () { state.sizeCode = x.code; ui.render(); }) + ">" +
        x.code + " · " + (p === null ? "пусто" : U.formatByn(p)) + (x.code === b.photoSize ? " · на фото" : "") + "</button>";
    });
    h += "</div></div></div>";
    h += '<label class="check"><input type="checkbox"' + on_("checked", b.photoSize === code) + on("change", function () { set({ photoSize: code }); }) +
      "> На фото размер " + code + ": его цена в каталоге, он выбран на странице букета</label>";
    h += '<label class="field"><span>Подпись размера на сайте</span><input class="input" data-k="note-' + code + '" value="' + esc(s.note) + '" placeholder="Как на фото, 11 цветков"' +
      on("input", function (e) { var v = e.target.value; patchSize(function (x) { return Object.assign({}, x, { note: v }); }); }) + "></label>";
    // Размер в сантиметрах (решение владельца 2026-11-15): на сайте плашка «↔ 35 см ↕ 40 см» в углу фото
    function cm(key, label) {
      return '<label class="field" style="flex: 1 1 140px"><span>' + label + '</span><input class="input" inputmode="numeric" maxlength="3" data-k="' + key + "-" + code + '" value="' + (s[key] ? s[key] : "") + '" placeholder="—"' +
        on("input", function (e) {
          var v = Math.min(500, parseInt(e.target.value.replace(/\D/g, ""), 10) || 0);
          patchSize(function (x) { var o = {}; o[key] = v; return Object.assign({}, x, o); });
        }) + "></label>";
    }
    h += '<div class="row" style="gap: 12px; align-items: flex-end">' + cm("widthCm", "↔ Ширина, см") + cm("heightCm", "↕ Высота, см") + "</div>";
    h += '<span class="small muted">Размер ' + code + " в сантиметрах. На сайте — плашка в углу фото, меняется вместе с выбранным размером. Пусто — плашки нет.</span>";
    if (s.items.length === 0) {
      h += '<p class="small muted">В размере ' + code + " пока нет ни одного цветка, поэтому на сайте его не видно. Найдите цветы ниже и укажите количество. Размер можно оставить пустым, если он не нужен.</p>";
    } else {
      h += '<table class="table"><thead><tr><th>Цветок</th><th>Количество</th><th>Цена</th><th>Сумма</th><th></th></tr></thead><tbody>';
      s.items.forEach(function (i) {
        var f = byId[i.flowerId];
        var rep = replacement(f);
        var qk = code + "-" + i.flowerId;
        var qt = state.qtyText[qk];
        if (qt === undefined || parseFloat(qt.replace(",", ".")) !== i.qty) qt = U.qtyText(i.qty);
        h += "<tr><td><b>" + esc(f ? f.name : "?") + '</b><div class="small muted">' + (f && f.kind === "GREEN" ? "Зелень" : f && f.kind === "DECOR" ? "Декор" : "Цветы") +
          (f && !f.inStock ? (rep ? " · нет, 🔁 заменим на: " + esc(rep.name.toLowerCase()) : " · нет в наличии, замены нет") : "") + "</div></td>";
        h += '<td><div class="qty"><button type="button"' + on("click", function () { setQty(i.flowerId, i.qty > 1 ? i.qty - 1 : 0.5); }) + ">−</button>" +
          '<input class="num" style="width: 52px; border: 0; text-align: center" data-k="qty-' + qk + '" value="' + esc(qt) + '" inputmode="decimal"' +
          on("input", function (e) {
            // Количество можно дробно: «1,5». Пока набирают, держим текст; число отдаём, когда оно понятно.
            state.qtyText[qk] = e.target.value;
            var n = parseFloat(e.target.value.replace(",", "."));
            if (n > 0) setQty(i.flowerId, Math.round(n * 100) / 100);
            else ui.render();
          }) +
          ">" +
          '<button type="button"' + on("click", function () { setQty(i.flowerId, i.qty + 1); }) + ">+</button></div></td>";
        h += '<td class="small muted">' + (f ? U.formatByn(f.priceKop) + " / " + (f.unit === "STEM" ? "ветка" : "шт") : "") + "</td>";
        h += '<td style="font-weight: 700">' + (f ? U.formatByn(Math.round(f.priceKop * i.qty)) : "") + "</td>";
        // словом, а не крестиком: на телефоне крестик было не найти (просьба владельца 2026-10-25)
        h += '<td class="del-cell"><button type="button" class="btn btn-outline item-del"' +
          on("click", function () { patchSize(function (x) { return Object.assign({}, x, { items: x.items.filter(function (y) { return y.flowerId !== i.flowerId; }) }); }); }) + ">Удалить</button></td></tr>";
      });
      h += "</tbody></table>";
    }
    h += '<div class="stack" style="gap: 8px"><label class="field"><span>Добавить цветок</span><input class="input" type="search" data-k="flower-query" value="' + esc(state.flowerQuery) +
      '" placeholder="Начните писать: роза, эвкалипт…"' +
      on("input", function (e) { state.flowerQuery = e.target.value; ui.render(); }) +
      on("keydown", function (e) {
        // Enter добавляет первый найденный цветок
        if (e.key === "Enter") {
          e.preventDefault();
          if (state.flowerQuery.trim() && candidates[0]) addFlower(candidates[0].id);
        }
      }) + "></label><div class=\"row\" style=\"gap: 6px\">";
    candidates.forEach(function (f) {
      h += '<button type="button" class="chip" style="font-size: 14px; min-height: 36px; padding: 6px 12px"' + on("click", function () { addFlower(f.id); }) + ">" +
        esc(f.name) + (f.inStock ? "" : " · нет в наличии") + "</button>";
    });
    if (candidates.length === 0) {
      h += '<span class="small muted">' + (state.flowerQuery.trim() ? "Такого цветка нет в справочнике. Добавьте его в разделе «Цветы»." : "Все цветы из справочника уже в составе.") + "</span>";
    }
    h += "</div></div>";
    if (code !== "M" && s.items.length === 0) {
      h += '<button type="button" class="btn btn-outline" style="align-self: flex-start"' + on("click", function () {
        var m = b.sizes.filter(function (x) { return x.code === "M"; })[0];
        patchSize(function (x) { return Object.assign({}, x, { items: m.items.map(function (i) { return { flowerId: i.flowerId, qty: i.qty }; }) }); });
      }) + ">Скопировать состав из M</button>";
    }
    var miss = missing(s);
    if (miss.length) h += '<p class="error">Нет в наличии: ' + esc(miss.join(", ")) + ". Замены в этом букете нет, поэтому размер скрыт на сайте.</p>";
    h += "</div>";
    h += swapsView(on);
    h += "</div>";

    // Правая колонка: цена и коллекция
    h += '<div class="stack" style="gap: 16px; position: sticky; top: 16px"><div class="card stack" style="gap: 10px">';
    h += '<div class="sum-row"><span>Цветы (' + code + ")</span><b>" + U.formatByn(sum) + "</b></div>";
    h += '<label class="sum-row" style="align-items: center"><span>Упаковка (' + code + '), BYN</span><input class="num" data-k="packaging-' + code + '" value="' + esc(state.packagingText[code]) + '" inputmode="decimal"' +
      on("input", function (e) {
        var v = e.target.value;
        state.packagingText[code] = v;
        var k = U.parseByn(v);
        if (k !== null) patchSize(function (x) { return Object.assign({}, x, { packagingKop: k }); });
        else ui.render();
      }) + "></label>";
    h += '<div class="sum-row" style="border-top: 1px solid var(--line); padding-top: 10px; align-items: baseline"><b>Цена на сайте</b><span class="sum-total">' +
      (s.items.length ? U.formatByn(U.roundPriceKop(sum + s.packagingKop)) : "—") + "</span></div>";
    h += '<span class="small muted">Цветы ' + U.formatByn(sum) + " + упаковка " + U.formatByn(s.packagingKop) + " = " + U.formatByn(sum + s.packagingKop) +
      ", на сайте округляем вверх до 5 BYN. У каждого размера своя упаковка: переключите размер слева.</span></div>";

    h += '<div class="card stack" style="gap: 10px"><span class="label">Коллекция</span><select class="input"' +
      on("change", function (e) { set({ collectionId: e.target.value ? Number(e.target.value) : null }); }) + '><option value="">Без коллекции</option>';
    collections.forEach(function (c) { h += '<option value="' + c.id + '"' + on_("selected", b.collectionId === c.id) + ">" + esc(c.name) + "</option>"; });
    h += '</select><span class="small muted">На сайте на странице букета — блок «Вся коллекция «…»»</span>';
    Object.keys(FORMATS).forEach(function (fmt) {
      var sib = siblings.filter(function (x) { return x.format === fmt; })[0];
      h += '<div class="sum-row small"><span>' + esc(FORMATS[fmt].label) + "</span>";
      if (sib && sib.id === b.id) h += '<span class="muted">Этот букет</span>';
      else if (sib) h += '<a href="/admin/bouquets/' + sib.id + '">' + esc(sib.name) + " · открыть</a>";
      else h += '<button type="button" class="btn-ghost small"' + on("click", function () { submit("/admin/bouquets/" + b.id + "/clone", { format: fmt }); }) + ">+ Создать с этим составом</button>";
      h += "</div>";
    });
    h += '<span class="small muted">Копия создаётся выключенной: добавьте фото и включите.</span></div>';
    h += '<a href="/bouquet/' + esc(initial.slug) + '" target="_blank" class="small">Посмотреть на сайте →</a>';
    h += '<button type="button" class="btn-ghost small muted" style="align-self: flex-start"' + on("click", function () {
      if (confirm("Удалить букет совсем? Заказы с ним сохранятся.")) submit("/admin/bouquets/" + b.id + "/delete", {});
    }) + ">Удалить букет</button>";
    h += "</div></div>";
    return h;
  }

  // Замены в этом букете: для каждого цветка свой пул. Цветок закрыли в «Цветах» — есть замена в наличии,
  // букет остаётся на сайте с пометкой «будет замена» (флорист увидит её в заказе); замены нет — размер скрыт.
  function swapsView(on) {
    var list = bouquetFlowers();
    var h = '<div class="card stack" style="gap: 12px"><span class="label" style="font-size: 18px">🔁 Замены в этом букете</span>';
    h += '<span class="small muted">Если цветок закончится, чем его можно заменить именно в этом букете. Можно несколько, первая в наличии и пойдёт в букет. ' +
      "Без замены: цветка нет — букет скрывается с сайта. Главный цветок (например, в монобукете) оставьте без замены.</span>";
    if (!list.length) return h + '<span class="small muted">Сначала добавьте цветы в состав.</span></div>';
    var mains = list.filter(function (f) { return f.kind === "FLOWER"; });
    list.forEach(function (f) {
      var ids = swapsOf(f.id);
      var rep = replacement(f);
      var status;
      var fOnly = flowerOnly(f);
      if (f.inStock && fOnly) status = '<span class="pill onlyshop">📍 ' + esc(shopLabel(fOnly)) + " · замены нет, букет тоже</span>";
      else if (f.inStock && f.onlyShop) status = '<span class="small muted">📍 ' + esc(shopLabel(f.onlyShop)) + " · в другом филиале будет замена</span>";
      else if (f.inStock) status = ids.length ? '<span class="small muted">Есть в наличии</span>' : '<span class="small muted">Есть в наличии · без замены</span>';
      else if (rep) status = '<span class="pill swap">Нет · 🔁 будет замена: ' + esc(rep.name.toLowerCase()) + "</span>";
      else status = '<span class="pill warn">Нет · ' + (ids.length ? "замен тоже нет" : "без замены") + ", букет скрыт</span>";
      h += '<div class="stack" style="gap: 6px; padding-bottom: 10px; border-bottom: 1px solid var(--line-soft)">';
      h += '<div class="row between" style="gap: 8px"><b>' + esc(f.name) + (mains.length === 1 && mains[0].id === f.id ? ' <span class="small muted">· монобукет</span>' : "") + "</b>" + status + "</div>";
      h += '<div class="row" style="gap: 6px; align-items: center">';
      if (!ids.length) h += '<span class="small muted">Без замены</span>';
      ids.forEach(function (id, n) {
        var x = byId[id];
        if (!x) return;
        h += '<span class="sub-chip ' + (x.inStock ? "" : "off") + '" title="' + (x.inStock ? "" : "Этой замены сейчас тоже нет") + '">' + (ids.length > 1 ? n + 1 + ". " : "") + esc(x.name) +
          (n > 0 ? '<button type="button" aria-label="Поднять выше" title="Выше: эту замену берём раньше"' + on("click", function () {
            var l = ids.slice(); l.splice(n, 1); l.splice(n - 1, 0, id); setSwaps(f.id, l);
          }) + ">▲</button>" : "") +
          '<button type="button" aria-label="Убрать замену"' + on("click", function () { setSwaps(f.id, ids.filter(function (y) { return y !== id; })); }) + ">×</button></span>";
      });
      h += '<select class="input" style="width: auto; min-height: 34px; padding: 4px 10px"' +
        on("change", function (e) { if (e.target.value) setSwaps(f.id, ids.concat([Number(e.target.value)])); }) + ">";
      h += '<option value="">' + (ids.length ? "+ ещё замена" : "+ указать замену") + "</option>";
      flowers.forEach(function (y) {
        if (y.id !== f.id && ids.indexOf(y.id) < 0) h += '<option value="' + y.id + '">' + esc(y.name) + (y.inStock ? "" : " (нет)") + "</option>";
      });
      h += "</select>";
      // подсказка из справочника «Цветы»: одним нажатием
      (f.substitutes || []).forEach(function (id) {
        var y = byId[id];
        if (!y || ids.indexOf(id) >= 0) return;
        h += '<button type="button" class="chip" style="font-size: 13px; min-height: 30px; padding: 4px 10px" title="Замена из справочника цветов"' +
          on("click", function () { setSwaps(f.id, ids.concat([id])); }) + ">+ " + esc(y.name) + "</button>";
      });
      h += "</div></div>";
    });
    h += '<span class="small muted">Цена букета при замене не меняется. Покупатель видит в составе «розовая (вместо: белая)», флорист — «🔁 Будет замена» в заказе, письме и Telegram.</span>';
    return h + "</div>";
  }

  // Таймер «собран сегодня» (решение владельца 2026-10-28): в срок букет сам выключается, не удаляется; включают вручную
  function timerView(on) {
    var b = state.b;
    var hours = parseInt(state.timerText, 10);
    var okHours = hours >= 1 && hours <= 720 && String(hours) === state.timerText.trim();
    var t;
    if (typeof b.timer === "number") t = "После сохранения букет сам скроется с сайта через " + b.timer + " ч — " + timerDate(nowMs() + b.timer * 3600000) + ".";
    else if (b.timer === "off") t = "Таймер снимется после сохранения, букет останется на сайте.";
    else if (b.hideAt) t = "Скроется с сайта <b>" + timerDate(parseTime(b.hideAt)) + "</b> — через " + timerLeft(parseTime(b.hideAt)) + ".";
    else if (b.hiddenByTimerAt && !b.enabled) t = "Скрыт по таймеру " + timerDate(parseTime(b.hiddenByTimerAt)) + ". На сайт вернётся, только если включить «На сайте» вверху и сохранить.";
    else t = "Таймера нет. Для готовых букетов: в срок букет сам выключится с сайта (не удалится), обратно — только вручную.";
    var running = typeof b.timer === "number" || (b.hideAt && b.timer === "keep");
    var h = '<div class="card stack" style="gap: 10px' + (running ? "; border-color: #e8c35a; background: #fffaf0" : "") + '">';
    h += '<span class="label">⏱ Таймер «собран сегодня»</span><span class="small">' + t + "</span>";
    if (running && !b.enabled) h += '<span class="small muted">Букет сейчас выключен, на сайте его и так не видно.</span>';
    h += '<div class="row" style="gap: 8px; align-items: center"><span class="small">Скрыть через</span>' +
      '<input class="input" data-k="timer-hours" inputmode="numeric" style="width: 80px" value="' + esc(state.timerText) + '"' +
      on("input", function (e) { state.timerText = e.target.value; ui.render(); }) + '><span class="small">ч</span>';
    h += '<button type="button" class="btn-outline btn small"' + on_("disabled", !okHours) + on("click", function () { set({ timer: hours }); }) + ">" +
      (running ? "Поставить заново" : "Запустить таймер") + "</button>";
    if (running) h += '<button type="button" class="btn-ghost small"' + on("click", function () { set({ timer: initial.hideAt ? "off" : "keep" }); }) + ">× Снять таймер</button>";
    h += "</div>";
    if (!okHours) h += '<span class="small" style="color: var(--danger)">Часы — целое число от 1 до 720 (30 дней)</span>';
    h += '<span class="small muted">Отсчёт — с момента сохранения. ' + data.timerHours + " ч = полтора суток. Ставится сам, когда отмечаете «Букет дня».</span></div>";
    return h;
  }

  var ui = U.mount(document.getElementById("admin-app"), view);
  // Количество: ушли из поля — показываем число как есть («1,» → «1»). Без перерисовки, чтобы не сбить нажатие кнопки.
  document.getElementById("admin-app").addEventListener("focusout", function (e) {
    var k = e.target.getAttribute && e.target.getAttribute("data-k");
    if (!k || k.slice(0, 4) !== "qty-") return;
    var parts = k.slice(4).split("-");
    var s = state.b.sizes.filter(function (x) { return x.code === parts[0]; })[0];
    var item = s && s.items.filter(function (i) { return String(i.flowerId) === parts[1]; })[0];
    delete state.qtyText[k.slice(4)];
    if (item) e.target.value = U.qtyText(item.qty);
  });
  setInterval(function () { if (state.b.hideAt) ui.render(); }, 60000); // «через …» идёт сам
})();
