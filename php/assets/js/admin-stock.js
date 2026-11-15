// «Закупка и списание»: ввод накладной (mode = purchase) и списания (mode = writeoff). Маршруты — routes/admin-stock.php.
// Фото накладной или тетради → сервер распознаёт → строки появляются здесь черновиком. Сохраняет только кнопка «Сохранить».
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var data = U.json("admin-data");
  var shops = data.shops;
  var token = (document.querySelector('meta[name="csrf"]') || {}).content || "";
  var uid = 0;

  var state = {
    items: data.items,
    head: { docDate: data.today, supplier: "", docNumber: "", total: "", comment: "" },
    lines: [],
    split: {}, // ключ позиции → { код магазина: текст }
    date: data.today,
    shop: shops[0] ? shops[0].code : "",
    rows: [],
    busy: "", // что сейчас делаем: «Распознаём…», «Сохраняем…»
    msg: null,
  };

  // ---------- числа ----------
  function num(s) {
    var t = String(s == null ? "" : s).replace(/\s/g, "").replace(",", ".");
    if (!/^\d+(\.\d{1,2})?$/.test(t)) return null;
    return parseFloat(t);
  }
  function norm(s) {
    return String(s || "").toLowerCase().replace(/ё/g, "е").replace(/[«»"“”]/g, "").replace(/\s+/g, " ").trim();
  }
  function itemById(id) {
    for (var i = 0; i < state.items.length; i++) if (state.items[i].id === id) return state.items[i];
    return null;
  }

  // ---------- выбор позиции ----------
  function itemSelect(row, on, set, k) {
    var h = '<select class="input" data-k="' + k + '" style="padding: 8px 10px; min-width: 190px"' + on("change", function (e) {
      var v = e.target.value;
      if (v.indexOf("n:") === 0) { var p = pendingNew()[v.slice(2)]; set({ itemSel: "new", newName: p.name, newKind: p.kind }); return; }
      set({ itemSel: v === "new" || v === "" ? v : Number(v), newName: v === "new" && !row.newName ? row.name : row.newName });
    }) + ' aria-invalid="' + (row.itemSel === "" ? "true" : "false") + '">';
    h += '<option value=""' + (row.itemSel === "" ? " selected" : "") + ">— выберите —</option>";
    h += '<option value="new"' + (row.itemSel === "new" ? " selected" : "") + ">+ Новая позиция…</option>";
    // новые позиции, которые уже вписали в других строках (ещё не сохранены): чтобы не печатать название заново
    var pend = pendingNew();
    var pendKeys = Object.keys(pend).filter(function (n) { return !(row.itemSel === "new" && norm(row.newName) === n); });
    if (pendKeys.length) {
      h += '<optgroup label="Новые (в этой накладной)">';
      pendKeys.forEach(function (n) { h += '<option value="n:' + esc(n) + '">' + esc(pend[n].name) + "</option>"; });
      h += "</optgroup>";
    }
    Object.keys(data.kinds).forEach(function (kind) {
      var list = state.items.filter(function (i) { return i.kind === kind; });
      if (!list.length) return;
      h += '<optgroup label="' + esc(data.kinds[kind]) + '">';
      list.forEach(function (i) { h += '<option value="' + i.id + '"' + (row.itemSel === i.id ? " selected" : "") + ">" + esc(i.name) + "</option>"; });
      h += "</optgroup>";
    });
    h += "</select>";
    if (row.itemSel === "new") {
      h += '<div class="row" style="margin-top: 6px; gap: 6px"><input class="input" style="padding: 8px 10px; flex: 1; min-width: 140px" maxlength="120" placeholder="Название, например «Коробка бархат S»" data-k="' + k + '-nn" value="' + esc(row.newName) + '"' +
        on("input", function (e) { set({ newName: e.target.value }); }) + ">";
      h += '<select class="input" style="padding: 8px 32px 8px 10px; width: auto; min-width: 130px" data-k="' + k + '-nk"' + on("change", function (e) { set({ newKind: e.target.value }); }) + ">";
      Object.keys(data.kinds).forEach(function (kind) { h += '<option value="' + kind + '"' + (row.newKind === kind ? " selected" : "") + ">" + esc(data.kinds[kind]) + "</option>"; });
      h += "</select></div>";
    }
    return h;
  }
  function pendingNew() {
    var out = {};
    state.lines.concat(state.rows).forEach(function (r) {
      if (r.itemSel === "new" && norm(r.newName)) out[norm(r.newName)] = { name: r.newName.trim(), kind: r.newKind };
    });
    return out;
  }
  function rowKey(row) {
    if (row.itemSel === "new") return norm(row.newName) ? "new:" + norm(row.newName) : "";
    return row.itemSel === "" ? "" : String(row.itemSel);
  }
  function rowLabel(row) {
    if (row.itemSel === "new") return row.newName.trim() + " (новая)";
    var i = itemById(row.itemSel);
    return i ? i.name : "";
  }

  // ---------- фото ----------
  function shrink(file) {
    return window.ArtfloraAdmin.prepareImage(file).then(function (f) {
      return new Promise(function (resolve, reject) {
        var url = URL.createObjectURL(f);
        var img = new Image();
        img.onload = function () {
          var k = Math.min(1, 2000 / Math.max(img.naturalWidth, img.naturalHeight));
          var c = document.createElement("canvas");
          c.width = Math.round(img.naturalWidth * k);
          c.height = Math.round(img.naturalHeight * k);
          c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
          URL.revokeObjectURL(url);
          c.toBlob(function (b) { b ? resolve(b) : reject(new Error("Не удалось обработать фото")); }, "image/jpeg", 0.85);
        };
        img.onerror = function () { URL.revokeObjectURL(url); reject(new Error("Не удалось открыть фото")); };
        img.src = url;
      });
    });
  }
  function recognize(kind, files, done) {
    files = Array.prototype.slice.call(files || []);
    if (!files.length) return;
    if (files.length > 6) { state.msg = { ok: false, text: "Не больше 6 фото за раз" }; ui.render(); return; }
    state.busy = "Распознаём фото… Это занимает до минуты.";
    state.msg = null;
    ui.render();
    Promise.all(files.map(shrink)).then(function (blobs) {
      var fd = new FormData();
      fd.append("kind", kind);
      fd.append("_csrf", token);
      blobs.forEach(function (b, i) { fd.append("files[]", b, "photo" + i + ".jpg"); });
      return fetch("/admin/stock/recognize", { method: "POST", headers: { "X-CSRF": token }, body: fd }).then(function (r) {
        return r.json().catch(function () { return { ok: false, error: r.status === 413 ? "Фото слишком большие, загрузите по одному" : "Сервер ответил ошибкой " + r.status }; });
      });
    }).catch(function (e) {
      return { ok: false, error: (e && e.message) || "Нет связи с сервером" };
    }).then(function (r) {
      state.busy = "";
      if (!r.ok) state.msg = { ok: false, text: r.error || "Не удалось распознать" };
      else done(r.data);
      ui.render();
    });
  }
  function photoButton(label, kind, on, done) {
    if (!data.recognize) return "";
    return '<label class="btn btn-outline" style="cursor: pointer"' + (state.busy ? ' aria-disabled="true"' : "") + ">📷 " + esc(label) +
      '<input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple hidden' +
      on("change", function (e) { var f = Array.prototype.slice.call(e.target.files || []); e.target.value = ""; if (!state.busy) recognize(kind, f, done); }) + "></label>";
  }

  // ---------- сохранение ----------
  function save(url, payload) {
    state.busy = "Сохраняем…";
    state.msg = null;
    ui.render();
    U.post(url, payload).then(function (r) {
      state.busy = "";
      if (r.ok) { window.location.href = r.redirect; return; }
      state.msg = { ok: false, text: r.error || "Ошибка" };
      ui.render();
    });
  }

  // ================= Накладная =================
  function newLine(p) {
    return Object.assign({ uid: ++uid, name: "", itemSel: "", newName: "", newKind: "PACK", qty: "", sum: "" }, p || {});
  }
  function groups() {
    var out = [];
    var by = {};
    state.lines.forEach(function (l) {
      var key = rowKey(l);
      if (!key) return;
      if (!by[key]) { by[key] = { key: key, label: rowLabel(l), qty: 0, sum: 0 }; out.push(by[key]); }
      by[key].qty = Math.round((by[key].qty + (num(l.qty) || 0)) * 100) / 100;
      by[key].sum += U.parseByn(l.sum) || 0;
    });
    return out;
  }
  function splitOf(key) {
    return state.split[key] || (state.split[key] = {});
  }
  function splitTotal(key) {
    var s = splitOf(key);
    var t = 0;
    shops.forEach(function (sh) { t += num(s[sh.code]) || 0; });
    return Math.round(t * 100) / 100;
  }
  function fillSplit(fn) {
    groups().forEach(function (g) { state.split[g.key] = fn(g); });
    ui.render();
  }
  function onInvoice(d) {
    if (d.supplier) state.head.supplier = d.supplier;
    if (d.docNumber) state.head.docNumber = d.docNumber;
    if (d.docDate) state.head.docDate = d.docDate;
    if (d.totalKop) state.head.total = U.kopToInput(d.totalKop);
    var lines = (d.lines || []).map(function (l) {
      return newLine({ name: l.name || "", itemSel: l.itemId || "", qty: l.qty ? U.qtyText(l.qty) : "", sum: l.sumKop ? U.kopToInput(l.sumKop) : "" });
    });
    // пустые строки, которые добавили вручную до фото, убираем
    state.lines = state.lines.filter(function (l) { return l.name || l.qty || l.sum || l.itemSel !== ""; }).concat(lines);
    var unknown = lines.filter(function (l) { return l.itemSel === ""; }).length;
    state.msg = { ok: true, text: "Распознано строк: " + lines.length + (unknown ? ". У " + unknown + " выберите позицию — сайт запомнит выбор для следующих накладных." : ". Проверьте и распишите по магазинам.") };
  }
  function onSplit(d) {
    var gs = groups();
    var used = 0;
    var miss = [];
    (d.lines || []).forEach(function (l) {
      var g = null;
      gs.forEach(function (x) {
        if (g) return;
        if ((l.itemId && x.key === String(l.itemId)) || norm(x.label.replace(/ \(новая\)$/, "")) === norm(l.name) || norm(x.label.replace(/ \(новая\)$/, "")) === norm(l.item)) g = x;
      });
      if (!g) { miss.push(l.name); return; }
      var s = {};
      shops.forEach(function (sh) { s[sh.code] = l.shops && l.shops[sh.code] ? U.qtyText(l.shops[sh.code]) : "0"; });
      state.split[g.key] = s;
      used++;
    });
    state.msg = { ok: !miss.length, text: "Расписано по магазинам: " + used + (miss.length ? ". Не нашли в накладной: " + miss.join(", ") + " — проверьте позиции строк." : ".") };
  }

  // Блок из чата: { supplier, docNumber, docDate, totalKop, lines: [{ name, item, kind, qty, sumKop }], shops: { позиция: { код: кол-во } } }
  function onPaste(d) {
    var byName = {};
    state.items.forEach(function (i) { byName[norm(i.name)] = i.id; });
    state.head = { docDate: d.docDate || state.head.docDate, supplier: d.supplier || "", docNumber: d.docNumber || "", total: d.totalKop ? U.kopToInput(d.totalKop) : "", comment: d.comment || "" };
    var keyOf = {};
    state.lines = d.lines.map(function (l) {
      var id = byName[norm(l.item)];
      var row = newLine({ name: l.name || "", itemSel: id || "new", newName: id ? "" : String(l.item || ""), newKind: data.kinds[l.kind] ? l.kind : "FLOWER", qty: l.qty ? U.qtyText(l.qty) : "", sum: l.sumKop != null ? U.kopToInput(l.sumKop) : "" });
      keyOf[norm(l.item)] = rowKey(row);
      return row;
    });
    state.split = {};
    Object.keys(d.shops || {}).forEach(function (name) {
      var key = keyOf[norm(name)];
      if (!key) return;
      var s = {};
      shops.forEach(function (sh) { s[sh.code] = U.qtyText(Number(d.shops[name][sh.code]) || 0); });
      state.split[key] = s;
    });
    var fresh = state.lines.filter(function (l) { return l.itemSel === "new"; }).map(function (l) { return l.newName; });
    fresh = fresh.filter(function (n, i) { return fresh.indexOf(n) === i; });
    state.msg = { ok: true, text: "Заполнено строк: " + state.lines.length + "." + (fresh.length ? " Новые позиции: " + fresh.join(", ") + ". Если такая уже есть в списке под другим названием — выберите её в строке." : "") + " Проверьте и нажмите «Сохранить накладную»." };
  }

  function purchaseView(on) {
    var h = '<div class="row between"><h1>Новая накладная</h1><a class="btn-ghost" href="/admin/stock">← Финансы</a></div>';
    h += '<span class="muted">Сфотографируйте накладную (можно все страницы сразу) — строки заполнятся сами. Или впишите вручную. Суммы — с НДС, как заплатили.</span>';
    if (!data.recognize) h += '<p class="small muted">Распознавание фото не подключено (нет ключа в настройках сайта), строки вносятся вручную.</p>';
    if (state.busy) h += '<p class="pill" style="align-self: flex-start">' + esc(state.busy) + "</p>";
    h += U.msgHtml(state.msg, "align-self: flex-start; white-space: normal; border-radius: 12px");

    h += '<div class="row">' + photoButton("Фото накладной", "invoice", on, onInvoice);
    // Накладную разобрал Claude в чате: вставляют готовый блок — строки и разбивка по магазинам заполняются сами
    h += '<button type="button" class="btn btn-outline"' + on("click", function () { state.pasteOpen = !state.pasteOpen; ui.render(); }) + ">Вставить из чата</button></div>";
    if (state.pasteOpen) {
    h += '<div class="card stack" style="gap: 8px">';
    h += '<span class="small muted">Пришлите фото накладной и тетради в чат с Claude, скопируйте блок из ответа и вставьте сюда. Потом проверьте строки и нажмите «Сохранить».</span>';
    h += '<textarea class="input" rows="4" data-k="paste" placeholder="Вставьте блок из чата" style="font-family: monospace; font-size: 12px"' + on("input", function (e) { state.paste = e.target.value; }) + ">" + esc(state.paste || "") + "</textarea>";
    h += '<button type="button" class="btn" style="align-self: flex-start"' + on("click", function () {
      var d = null;
      try { d = JSON.parse(String(state.paste || "").trim().replace(/^```\w*\s*|\s*```$/g, "")); } catch (err) { d = null; }
      if (!d || !Array.isArray(d.lines)) { state.msg = { ok: false, text: "Не получилось прочитать блок. Скопируйте его из чата целиком." }; ui.render(); return; }
      onPaste(d); state.pasteOpen = false; state.paste = ""; ui.render();
    }) + ">Заполнить</button></div>";
    }

    var hd = state.head;
    function setHead(p) { Object.assign(state.head, p); ui.render(); }
    h += '<div class="card row" style="gap: 12px; align-items: flex-end">';
    h += '<label class="stack" style="gap: 4px"><span class="small muted">Дата</span><input class="input" type="date" data-k="h-date" value="' + esc(hd.docDate) + '"' + on("input", function (e) { setHead({ docDate: e.target.value }); }) + "></label>";
    h += '<label class="stack" style="gap: 4px; flex: 1; min-width: 180px"><span class="small muted">Поставщик</span><input class="input" maxlength="160" data-k="h-sup" value="' + esc(hd.supplier) + '"' + on("input", function (e) { setHead({ supplier: e.target.value }); }) + "></label>";
    h += '<label class="stack" style="gap: 4px"><span class="small muted">№ накладной</span><input class="input" maxlength="60" style="width: 140px" data-k="h-num" value="' + esc(hd.docNumber) + '"' + on("input", function (e) { setHead({ docNumber: e.target.value }); }) + "></label>";
    h += '<label class="stack" style="gap: 4px"><span class="small muted">Итого с НДС по накладной</span><input class="num" style="width: 140px" inputmode="decimal" data-k="h-total" value="' + esc(hd.total) + '"' + on("input", function (e) { setHead({ total: e.target.value }); }) + "></label>";
    h += "</div>";

    // строки
    h += '<div class="card stack" style="gap: 10px"><h3>Строки накладной</h3>';
    h += '<table class="table"><thead><tr><th>В накладной</th><th>Позиция у нас</th><th>Кол-во</th><th>Сумма с НДС</th><th></th></tr></thead><tbody>';
    state.lines.forEach(function (l, i) {
      function set(p) { state.lines = state.lines.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; }); ui.render(); }
      var k = "l" + l.uid;
      h += "<tr>";
      h += '<td><input class="input" style="padding: 8px 10px; min-width: 240px" maxlength="190" placeholder="как в накладной" title="' + esc(l.name) + '" data-k="' + k + '-n" value="' + esc(l.name) + '"' + on("input", function (e) { set({ name: e.target.value }); }) + "></td>";
      h += "<td>" + itemSelect(l, on, set, k + "-i") + "</td>";
      h += '<td><input class="num" inputmode="decimal" data-k="' + k + '-q" value="' + esc(l.qty) + '"' + (num(l.qty) ? "" : ' aria-invalid="true"') + on("input", function (e) { set({ qty: e.target.value }); }) + "></td>";
      h += '<td><input class="num" style="width: 110px" inputmode="decimal" data-k="' + k + '-s" value="' + esc(l.sum) + '"' + (U.parseByn(l.sum) === null ? ' aria-invalid="true"' : "") + on("input", function (e) { set({ sum: e.target.value }); }) + "></td>";
      h += '<td><button type="button" class="btn-ghost" aria-label="Убрать строку"' + on("click", function () { state.lines = state.lines.filter(function (_, j) { return j !== i; }); ui.render(); }) + ">×</button></td>";
      h += "</tr>";
    });
    h += "</tbody></table>";
    h += '<button type="button" class="btn-ghost small" style="align-self: flex-start"' + on("click", function () { state.lines = state.lines.concat([newLine()]); ui.render(); }) + ">+ Строка</button>";
    var sum = state.lines.reduce(function (a, l) { return a + (U.parseByn(l.sum) || 0); }, 0);
    var total = U.parseByn(hd.total);
    h += '<div class="row"><b>Сумма строк: ' + esc(U.formatByn(sum)) + "</b>";
    if (total !== null && hd.total !== "") h += sum === total ? '<span class="pill ok">✓ сходится с накладной</span>' : '<span class="pill warn">не сходится с накладной: разница ' + esc(U.formatByn(sum - total)) + "</span>";
    h += "</div></div>";

    // по магазинам
    var gs = groups();
    h += '<div class="card stack" style="gap: 10px"><div class="row between"><h3>Куда что ушло</h3><div class="row">';
    shops.forEach(function (sh) {
      h += '<button type="button" class="chip chip-sm"' + on("click", function () {
        fillSplit(function (g) { var s = {}; shops.forEach(function (x) { s[x.code] = x.code === sh.code ? U.qtyText(g.qty) : "0"; }); return s; });
      }) + ">Всё — " + esc(sh.name) + "</button>";
    });
    if (shops.length === 2) {
      h += '<button type="button" class="chip chip-sm"' + on("click", function () {
        fillSplit(function (g) { var a = Math.ceil(g.qty / 2); var s = {}; s[shops[0].code] = U.qtyText(a); s[shops[1].code] = U.qtyText(Math.round((g.qty - a) * 100) / 100); return s; });
      }) + ">Пополам</button>";
    }
    h += photoButton("Фото из тетради", "split", on, onSplit);
    h += "</div></div>";
    if (!gs.length) h += '<p class="small muted">Сначала выберите позиции в строках накладной.</p>';
    else {
      h += '<table class="table"><thead><tr><th>Позиция</th><th>Пришло</th>';
      shops.forEach(function (sh) { h += "<th>" + esc(sh.name) + "</th>"; });
      h += "<th></th></tr></thead><tbody>";
      gs.forEach(function (g) {
        var s = splitOf(g.key);
        var ok = Math.abs(splitTotal(g.key) - g.qty) < 0.001;
        h += "<tr><td>" + esc(g.label) + '</td><td><b>' + esc(U.qtyText(g.qty)) + "</b></td>";
        shops.forEach(function (sh) {
          h += '<td><input class="num" inputmode="decimal" data-k="sp-' + esc(g.key) + "-" + sh.code + '" value="' + esc(s[sh.code] || "") + '"' + (ok ? "" : ' aria-invalid="true"') +
            on("input", function (e) { splitOf(g.key)[sh.code] = e.target.value; ui.render(); }) + "></td>";
        });
        h += "<td>" + (ok ? '<span class="pill ok">✓</span>' : '<span class="pill warn">расписано ' + esc(U.qtyText(splitTotal(g.key))) + "</span>") + "</td></tr>";
      });
      h += "</tbody></table>";
    }
    h += "</div>";

    h += '<label class="stack" style="gap: 4px"><span class="small muted">Комментарий (необязательно)</span><input class="input" maxlength="300" data-k="h-com" value="' + esc(hd.comment) + '"' + on("input", function (e) { setHead({ comment: e.target.value }); }) + "></label>";
    h += '<div class="row"><button type="button" class="btn btn-lg"' + U.attr("disabled", !!state.busy) + on("click", savePurchase) + ">Сохранить накладную</button></div>";
    return h;
  }

  function savePurchase() {
    var hd = state.head;
    var err = null;
    if (!state.lines.length) err = "Добавьте хотя бы одну строку";
    var lines = state.lines.map(function (l, i) {
      var q = num(l.qty), s = U.parseByn(l.sum), key = rowKey(l);
      if (!err && !key) err = "Строка " + (i + 1) + ": выберите позицию";
      if (!err && (!q || s === null)) err = "Строка " + (i + 1) + ": проверьте количество и сумму";
      return { name: l.name, itemId: typeof l.itemSel === "number" ? l.itemSel : null, newName: l.itemSel === "new" ? l.newName : null, newKind: l.newKind, qty: q, sumKop: s, key: key };
    });
    var split = {};
    groups().forEach(function (g) {
      if (!err && Math.abs(splitTotal(g.key) - g.qty) > 0.001) err = "«" + g.label + "»: распишите по магазинам (пришло " + U.qtyText(g.qty) + ", расписано " + U.qtyText(splitTotal(g.key)) + ")";
      split[g.key] = {};
      shops.forEach(function (sh) { split[g.key][sh.code] = num(splitOf(g.key)[sh.code]) || 0; });
    });
    var total = hd.total.trim() === "" ? null : U.parseByn(hd.total);
    if (!err && hd.total.trim() !== "" && total === null) err = "Проверьте «Итого» накладной";
    if (err) { state.msg = { ok: false, text: err }; ui.render(); return; }
    save("/admin/stock/purchase/save", { docDate: hd.docDate, supplier: hd.supplier, docNumber: hd.docNumber, docTotalKop: total, comment: hd.comment, lines: lines, split: split });
  }

  // ================= Списание =================
  function newRow(p) {
    return Object.assign({ uid: ++uid, name: "", itemSel: "", newName: "", newKind: "FLOWER", shop: state.shop, qty: "", reason: "wilted", comment: "" }, p || {});
  }
  function onWriteoff(d) {
    if (d.date) state.date = d.date;
    var rows = (d.lines || []).map(function (l) {
      return newRow({ name: l.name || "", itemSel: l.itemId || "", shop: l.shop || state.shop, qty: l.qty ? U.qtyText(l.qty) : "", reason: l.reason || "wilted" });
    });
    state.rows = state.rows.filter(function (r) { return r.qty || r.itemSel !== ""; }).concat(rows);
    var unknown = rows.filter(function (r) { return r.itemSel === ""; }).length;
    state.msg = { ok: true, text: "Распознано строк: " + rows.length + (unknown ? ". У " + unknown + " выберите, что это." : ". Проверьте магазин и количество.") };
  }
  function writeoffView(on) {
    var h = '<div class="row between"><h1>Списание</h1><a class="btn-ghost" href="/admin/stock">← Финансы</a></div>';
    h += '<span class="muted">Сфотографируйте лист списания из тетради — строки заполнятся сами. Или впишите вручную. Сумма посчитается по цене из последней накладной.</span>';
    if (!data.recognize) h += '<p class="small muted">Распознавание фото не подключено (нет ключа в настройках сайта), строки вносятся вручную.</p>';
    if (state.busy) h += '<p class="pill" style="align-self: flex-start">' + esc(state.busy) + "</p>";
    h += U.msgHtml(state.msg, "align-self: flex-start; white-space: normal; border-radius: 12px");
    h += '<div class="card row" style="gap: 12px; align-items: flex-end">';
    h += '<label class="stack" style="gap: 4px"><span class="small muted">Дата</span><input class="input" type="date" data-k="w-date" value="' + esc(state.date) + '"' + on("input", function (e) { state.date = e.target.value; ui.render(); }) + "></label>";
    h += '<label class="stack" style="gap: 4px"><span class="small muted">Магазин (для новых строк)</span><select class="input" style="width: auto" data-k="w-shop"' + on("change", function (e) { state.shop = e.target.value; ui.render(); }) + ">";
    shops.forEach(function (sh) { h += '<option value="' + sh.code + '"' + (state.shop === sh.code ? " selected" : "") + ">" + esc(sh.name) + "</option>"; });
    h += "</select></label>";
    h += photoButton("Фото списания", "writeoff", on, onWriteoff);
    h += "</div>";

    h += '<div class="card stack" style="gap: 10px">';
    h += '<table class="table"><thead><tr><th>Что</th><th>Магазин</th><th>Сколько</th><th>Причина</th><th></th></tr></thead><tbody>';
    state.rows.forEach(function (r, i) {
      function set(p) { state.rows = state.rows.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; }); ui.render(); }
      var k = "r" + r.uid;
      h += "<tr><td>" + (r.name ? '<div class="small muted">в тетради: ' + esc(r.name) + "</div>" : "") + itemSelect(r, on, set, k + "-i") + "</td>";
      h += '<td><select class="input" style="width: auto; padding: 8px 10px" data-k="' + k + '-sh"' + on("change", function (e) { set({ shop: e.target.value }); }) + ">";
      shops.forEach(function (sh) { h += '<option value="' + sh.code + '"' + (r.shop === sh.code ? " selected" : "") + ">" + esc(sh.name) + "</option>"; });
      h += "</select></td>";
      h += '<td><input class="num" inputmode="decimal" data-k="' + k + '-q" value="' + esc(r.qty) + '"' + (num(r.qty) ? "" : ' aria-invalid="true"') + on("input", function (e) { set({ qty: e.target.value }); }) + "></td>";
      h += '<td><select class="input" style="width: auto; padding: 8px 10px" data-k="' + k + '-re"' + on("change", function (e) { set({ reason: e.target.value }); }) + ">";
      Object.keys(data.reasons).forEach(function (c) { h += '<option value="' + c + '"' + (r.reason === c ? " selected" : "") + ">" + esc(data.reasons[c]) + "</option>"; });
      h += "</select></td>";
      h += '<td><button type="button" class="btn-ghost" aria-label="Убрать строку"' + on("click", function () { state.rows = state.rows.filter(function (_, j) { return j !== i; }); ui.render(); }) + ">×</button></td></tr>";
    });
    h += "</tbody></table>";
    h += '<button type="button" class="btn-ghost small" style="align-self: flex-start"' + on("click", function () { state.rows = state.rows.concat([newRow()]); ui.render(); }) + ">+ Строка</button>";
    h += "</div>";
    h += '<div class="row"><button type="button" class="btn btn-lg"' + U.attr("disabled", !!state.busy) + on("click", saveWriteoff) + ">Сохранить списание</button></div>";
    return h;
  }
  function saveWriteoff() {
    var err = state.rows.length ? null : "Добавьте хотя бы одну строку";
    var rows = state.rows.map(function (r, i) {
      if (!err && !rowKey(r)) err = "Строка " + (i + 1) + ": выберите, что списали";
      if (!err && !num(r.qty)) err = "Строка " + (i + 1) + ": укажите, сколько";
      return { name: r.name, itemId: typeof r.itemSel === "number" ? r.itemSel : null, newName: r.itemSel === "new" ? r.newName : null, newKind: r.newKind, shop: r.shop, qty: num(r.qty), reason: r.reason, comment: r.comment };
    });
    if (err) { state.msg = { ok: false, text: err }; ui.render(); return; }
    save("/admin/stock/writeoff/save", { date: state.date, rows: rows });
  }

  if (data.mode === "purchase") state.lines = [newLine()];
  else state.rows = [newRow()];
  var ui = U.mount(document.getElementById("admin-app"), data.mode === "purchase" ? purchaseView : writeoffView);
})();
