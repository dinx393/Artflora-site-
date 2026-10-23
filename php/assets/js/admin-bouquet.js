// Карточка букета = src/app/admin/(panel)/bouquets/[id]/BouquetEditor.tsx
// Фото, поводы, цвета, формат, состав по размерам XS–XXL, упаковка у каждого размера, коллекция.
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var on_ = U.attr;
  var data = U.json("admin-data");
  var flowers = data.flowers;
  var collections = data.collections;
  var siblings = data.siblings;
  var colors = data.colors;
  var FORMATS = data.formats;
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || "";

  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }
  var initial = clone(data.initial);
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
    flowerQuery: "",
  };
  var byId = {};
  flowers.forEach(function (f) { byId[f.id] = f; });

  function size() {
    return state.b.sizes.filter(function (s) { return s.code === state.sizeCode; })[0];
  }
  function flowersKop(s) {
    return s.items.reduce(function (sum, i) { return sum + Math.round(((byId[i.flowerId] || {}).priceKop || 0) * i.qty); }, 0);
  }
  // чем заменим цветок, которого нет: первая замена в наличии
  function replacement(f) {
    if (!f || f.inStock) return undefined;
    return f.substitutes.map(function (id) { return byId[id]; }).filter(function (x) { return x && x.inStock; })[0];
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
    U.post("/admin/bouquets/save", state.b).then(function (r) {
      state.pending = false;
      state.msg = r.ok ? { ok: true, text: "Сохранено, сайт обновлён" } : { ok: false, text: r.error || "Ошибка" };
      if (r.ok) {
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
      on("click", function () { set({ enabled: !b.enabled }); }) + "></button>" + (b.enabled ? "На сайте" : "Выключен") + "</span>";
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
      h += '<div class="ph"' + (i === 0 ? ' style="outline: 2px solid var(--cherry)"' : "") + '><img src="' + esc(p) + '" alt="">';
      h += '<button type="button" aria-label="Убрать фото"' + on("click", function () { set({ photos: b.photos.filter(function (_, n) { return n !== i; }) }); }) + ">×</button>";
      if (i > 0) {
        h += '<button type="button" style="left: 4px; right: auto; width: auto; border-radius: 999px; padding: 0 8px; font-size: 12px"' +
          on("click", function () { set({ photos: [p].concat(b.photos.filter(function (_, n) { return n !== i; })) }); }) + ">главное</button>";
      }
      h += "</div>";
    });
    h += '<label class="ph" style="display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: 700">' + (state.uploading ? "Загрузка…" : "+ Фото") +
      '<input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden' + on("change", function (e) { onUpload(e.target.files); }) + "></label></div>";

    // Поля
    h += '<label class="field"><span>Название</span><input class="input" data-k="name" value="' + esc(b.name) + '"' + on("input", function (e) { set({ name: e.target.value }); }) + "></label>";
    h += '<label class="field"><span>Адрес страницы: artflora.by/bouquet/…</span><input class="input" data-k="slug" value="' + esc(b.slug) + '"' +
      on("input", function (e) { set({ slug: e.target.value.toLowerCase() }); }) + "></label>";
    h += '<label class="field"><span>Описание</span><textarea class="input" rows="3" data-k="description" placeholder="Короткое описание для покупателя"' +
      on("input", function (e) { set({ description: e.target.value }); }) + ">" + esc(b.description) + "</textarea></label>";

    h += '<div class="stack" style="gap: 8px"><span class="label">Поводы</span><div class="row" style="gap: 6px">';
    data.occasions.forEach(function (o) {
      h += '<button type="button" class="chip ' + (b.occasions.indexOf(o.slug) >= 0 ? "on" : "") + '"' +
        on("click", function () { set({ occasions: toggleIn(b.occasions, o.slug) }); }) + ">" + esc(o.label) + "</button>";
    });
    h += "</div></div>";

    h += '<div class="stack" style="gap: 8px"><span class="label">Цвет букета <span class="small muted">(для фильтра в каталоге, можно несколько; свои цвета — в «Настройках»)</span></span><div class="row" style="gap: 6px">';
    colors.forEach(function (c) {
      h += '<button type="button" class="chip ' + (b.colors.indexOf(c.slug) >= 0 ? "on" : "") + '"' + on("click", function () { set({ colors: toggleIn(b.colors, c.slug) }); }) + ">" +
        '<i class="swatch" style="background: ' + esc(c.swatch) + '" aria-hidden="true"></i>' + esc(c.label) + "</button>";
    });
    h += "</div></div>";

    h += '<div class="row" style="gap: 12px"><label class="field" style="flex: 1 1 200px"><span>Формат</span><select class="input"' + on("change", function (e) { set({ format: e.target.value }); }) + ">";
    Object.keys(FORMATS).forEach(function (k) { h += '<option value="' + k + '"' + on_("selected", b.format === k) + ">" + esc(FORMATS[k].label) + "</option>"; });
    h += "</select></label>";
    h += '<label class="check" style="margin-top: 22px"><input type="checkbox"' + on_("checked", b.isHit) + on("change", function (e) { set({ isHit: e.target.checked }); }) + "> Хит недели</label>";
    h += '<label class="check" style="margin-top: 22px"><input type="checkbox"' + on_("checked", b.isExclusive) + on("change", function (e) { set({ isExclusive: e.target.checked }); }) + "> Эксклюзив недели</label></div>";

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
        h += "<tr><td><b>" + esc(f ? f.name : "?") + '</b><div class="small muted">' + (f && f.kind === "GREEN" ? "Зелень" : "Цветы") +
          (f && !f.inStock ? (rep ? " · нет, заменим на: " + esc(rep.name.toLowerCase()) : " · нет в наличии") : "") + "</div></td>";
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
        h += '<td><button type="button" class="btn-ghost" aria-label="Убрать"' +
          on("click", function () { patchSize(function (x) { return Object.assign({}, x, { items: x.items.filter(function (y) { return y.flowerId !== i.flowerId; }) }); }); }) + ">×</button></td></tr>";
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
    if (miss.length) h += '<p class="error">Нет в наличии: ' + esc(miss.join(", ")) + ". Этот размер скрыт на сайте.</p>";
    h += "</div></div>";

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
    h += '</select><span class="small muted">На сайте покажем как «Этот же состав в другом виде»</span>';
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
})();
