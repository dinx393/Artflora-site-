// Справочник цветов = src/app/admin/(panel)/flowers/FlowersEditor.tsx
// Цены, наличие, замены, порядок перетаскиванием и блок «Что изменится на сайте».
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var data = U.json("admin-data");
  var bouquets = data.bouquets;
  var initial = data.initial;

  function toRow(f) {
    return {
      id: f.id,
      key: String(f.id),
      name: f.name,
      kind: f.kind,
      unit: f.unit,
      priceText: U.kopToInput(f.priceKop),
      prevPriceKop: f.prevPriceKop,
      inStock: f.inStock,
      substitutes: f.substitutes.slice(),
    };
  }

  var state = { rows: initial.map(toRow), filter: "all", msg: null, pending: false, dragKey: null };

  // --- расчёт как в src/lib/pricing.ts (размер «на фото» здесь не учитываем, как и в Node-версии) ---
  var SIZE_ORDER = ["XS", "S", "M", "L", "XL", "XXL"];
  function replacementFor(f) {
    if (f.inStock) return false;
    for (var i = 0; i < f.substitutes.length; i++) if (f.substitutes[i].inStock) return f.substitutes[i].name;
    return null;
  }
  function missingFlowers(s) {
    return s.items.filter(function (i) { return replacementFor(i.flower) === null; }).map(function (i) { return i.flower.name; });
  }
  function availableSizes(b) {
    return b.sizes
      .filter(function (s) { return s.items.length > 0 && missingFlowers(s).length === 0; })
      .sort(function (a, c) { return SIZE_ORDER.indexOf(a.code) - SIZE_ORDER.indexOf(c.code); });
  }
  function sizePriceKop(s) {
    return U.roundPriceKop(s.items.reduce(function (sum, i) { return sum + Math.round(i.flower.priceKop * i.qty); }, 0) + s.packagingKop);
  }
  function visibility(b) {
    if (!b.enabled) return { visible: false, reason: "disabled" };
    if (availableSizes(b).length) return { visible: true };
    var missing = [];
    b.sizes.forEach(function (s) {
      missingFlowers(s).forEach(function (m) { if (missing.indexOf(m) < 0) missing.push(m); });
    });
    return { visible: false, reason: "no-flowers", missing: missing };
  }
  function defaultSize(b) {
    var av = availableSizes(b);
    return av.filter(function (s) { return s.code === "M"; })[0] || av[0] || null;
  }
  function toPriced(b, flowers) {
    return {
      enabled: b.enabled,
      sizes: b.sizes.map(function (s) {
        return {
          code: s.code,
          packagingKop: s.packagingKop,
          items: s.items.map(function (i) {
            var f = flowers[i.flowerId] || { name: "?", priceKop: 0, inStock: false, substitutes: [] };
            var subs = [];
            f.substitutes.forEach(function (id) { if (flowers[id]) subs.push({ name: flowers[id].name, inStock: flowers[id].inStock }); });
            return { qty: i.qty, flower: { name: f.name, priceKop: f.priceKop, inStock: f.inStock, substitutes: subs } };
          }),
        };
      }),
    };
  }
  function summary(b) {
    var s = defaultSize(b);
    return { vis: visibility(b), price: s ? sizePriceKop(s) : null };
  }

  var before = {};
  function setBefore(list) {
    before = {};
    list.forEach(function (f) { before[f.id] = { name: f.name, priceKop: f.priceKop, inStock: f.inStock, substitutes: f.substitutes }; });
  }
  setBefore(initial);

  var usage = {};
  bouquets.forEach(function (b) {
    var seen = {};
    b.sizes.forEach(function (s) { s.items.forEach(function (i) { seen[i.flowerId] = true; }); });
    Object.keys(seen).forEach(function (id) { usage[id] = (usage[id] || 0) + 1; });
  });

  function patch(key, p) {
    state.rows = state.rows.map(function (r) { return r.key === key ? Object.assign({}, r, p) : r; });
    ui.render();
  }
  function bump(key, delta) {
    state.rows = state.rows.map(function (r) {
      if (r.key !== key) return r;
      var k = Math.max(0, (U.parseByn(r.priceText) || 0) + delta);
      return Object.assign({}, r, { priceText: U.kopToInput(k) });
    });
    ui.render();
  }

  function save() {
    var bad = state.rows.filter(function (r) { return U.parseByn(r.priceText) === null || !r.name.trim(); })[0];
    if (bad) {
      state.msg = { ok: false, text: "Проверьте цену или название: " + (bad.name || "новый цветок") };
      return ui.render();
    }
    state.pending = true;
    ui.render();
    var body = state.rows.map(function (x) {
      return { id: x.id, name: x.name, kind: x.kind, unit: x.unit, priceKop: U.parseByn(x.priceText), inStock: x.inStock, substitutes: x.substitutes };
    });
    U.post("/admin/flowers/save", body).then(function (r) {
      state.pending = false;
      state.msg = r.ok ? { ok: true, text: "Сохранено, цены на сайте обновлены" } : { ok: false, text: r.error || "Ошибка" };
      if (r.ok && r.flowers) {
        // новые цветки получили номера в базе — без этого следующее сохранение создавало бы их второй раз
        state.rows = r.flowers.map(toRow);
        setBefore(r.flowers);
      }
      ui.render();
    });
  }

  function remove(r) {
    if (!r.id) {
      state.rows = state.rows.filter(function (x) { return x.key !== r.key; });
      return ui.render();
    }
    U.post("/admin/flowers/delete", { id: r.id }).then(function (res) {
      if (res.ok) state.rows = state.rows.filter(function (x) { return x.key !== r.key; });
      else state.msg = { ok: false, text: res.error === U.OFFLINE ? "Не удалось удалить: нет связи с сервером" : res.error || "Ошибка" };
      ui.render();
    });
  }

  function shown() {
    return state.rows.filter(function (r) { return state.filter === "all" || r.kind === state.filter; });
  }

  // тянем вниз — ставим после цели, вверх — перед ней
  function moveBefore(from, to) {
    if (from === to) return false;
    var rs = state.rows;
    var fromIdx = rs.findIndex(function (r) { return r.key === from; });
    var toIdx = rs.findIndex(function (r) { return r.key === to; });
    var item = rs[fromIdx];
    var rest = rs.filter(function (r) { return r.key !== from; });
    var at = rest.findIndex(function (r) { return r.key === to; });
    rest.splice(fromIdx < toIdx ? at + 1 : at, 0, item);
    state.rows = rest;
    return true;
  }
  function step(key, dir) {
    var list = shown();
    var i = list.findIndex(function (r) { return r.key === key; });
    var other = list[i + dir];
    if (other && moveBefore(key, other.key)) ui.render();
  }

  function plural(n) {
    return n === 1 ? "букет" : n < 5 ? "букета" : "букетов";
  }

  function view(on) {
    var after = {};
    state.rows.forEach(function (r) {
      if (r.id) after[r.id] = { name: r.name, priceKop: U.parseByn(r.priceText) || 0, inStock: r.inStock, substitutes: r.substitutes };
    });
    var changes = bouquets
      .map(function (b) { return { b: b, was: summary(toPriced(b, before)), now: summary(toPriced(b, after)) }; })
      .filter(function (c) {
        return c.was.price !== c.now.price || c.was.vis.visible !== c.now.vis.visible || (!c.now.vis.visible && c.now.vis.reason === "no-flowers");
      });
    var real = changes.filter(function (c) { return c.was.price !== c.now.price || c.was.vis.visible !== c.now.vis.visible; });

    var h = "";
    h += '<div class="row between"><div class="stack" style="gap: 4px"><h1>Цветы</h1>';
    h += '<span class="muted">Розничная цена за штуку или ветку. Меняете цену здесь, и все букеты с этим цветком пересчитаются сами.</span>';
    h += '<span class="small muted">Порядок меняется перетаскиванием за ⠿ (или стрелками ▲▼), потом «Сохранить».</span></div>';
    h += '<button type="button" class="btn"' + on("click", function () {
      state.rows.push({ key: "new-" + Date.now(), name: "", kind: "FLOWER", unit: "STEM", priceText: "0,00", prevPriceKop: null, inStock: true, substitutes: [] });
      ui.render();
    }) + ">+ Добавить цветок</button></div>";

    h += '<div class="tabs">';
    [["all", "Все"], ["FLOWER", "Цветы"], ["GREEN", "Зелень"]].forEach(function (t) {
      h += '<button type="button" class="chip ' + (state.filter === t[0] ? "on" : "") + '"' + on("click", function () { state.filter = t[0]; ui.render(); }) + ">" + t[1] + "</button>";
    });
    h += "</div>";

    h += '<div class="admin-grid"><div class="table-wrap"><table class="table"><thead><tr><th aria-label="Порядок"></th><th>Цветок</th><th>Цена, BYN</th><th>Было</th><th>Наличие</th><th>Букетов</th><th></th></tr></thead><tbody>';
    shown().forEach(function (r) {
      var k = r.key;
      h += '<tr data-row="' + esc(k) + '" class="' + (r.inStock ? "" : "off") + (state.dragKey === k ? " dragging" : "") + '"' +
        on("dragover", function (e) {
          if (!state.dragKey) return;
          e.preventDefault();
          // строки переставляем прямо на странице: перетаскиваемая строка должна остаться той же, иначе браузер прервёт перетаскивание
          if (state.dragKey !== k && moveBefore(state.dragKey, k)) {
            var rows = document.querySelectorAll("#admin-app tr[data-row]");
            var map = {};
            rows.forEach(function (tr) { map[tr.getAttribute("data-row")] = tr; });
            var body = rows[0].parentNode;
            shown().forEach(function (x) { if (map[x.key]) body.appendChild(map[x.key]); });
          }
        }) +
        on("drop", function (e) { e.preventDefault(); }) + ">";
      h += '<td class="drag-cell"><span class="drag-handle" draggable="true" title="Перетащите, чтобы поменять порядок"' +
        on("dragstart", function (e, el) {
          state.dragKey = k;
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData("text/plain", k);
          var tr = el.closest("tr");
          if (tr) {
            e.dataTransfer.setDragImage(tr, 20, 20);
            tr.classList.add("dragging");
          }
        }) +
        on("dragend", function () { state.dragKey = null; ui.render(); }) + ">⠿</span>";
      h += '<span class="drag-steps"><button type="button" aria-label="Выше"' + on("click", function () { step(k, -1); }) + '>▲</button><button type="button" aria-label="Ниже"' + on("click", function () { step(k, 1); }) + ">▼</button></span></td>";

      h += '<td><input class="input" style="padding: 8px 10px; font-weight: 700" data-k="name-' + esc(k) + '" value="' + esc(r.name) + '" placeholder="Название"' +
        on("input", function (e) { patch(k, { name: e.target.value }); }) + ">";
      h += '<div class="row small muted" style="gap: 6px; margin-top: 4px"><select style="border: 0; background: none"' + on("change", function (e) { patch(k, { kind: e.target.value }); }) + ">" +
        '<option value="FLOWER"' + U.attr("selected", r.kind === "FLOWER") + ">Цветы</option><option value=\"GREEN\"" + U.attr("selected", r.kind === "GREEN") + ">Зелень</option></select> · " +
        '<select style="border: 0; background: none"' + on("change", function (e) { patch(k, { unit: e.target.value }); }) + ">" +
        '<option value="STEM"' + U.attr("selected", r.unit === "STEM") + ">за ветку</option><option value=\"PIECE\"" + U.attr("selected", r.unit === "PIECE") + ">за штуку</option></select></div>";
      h += '<div class="subs small"><span class="muted">Замена:</span>';
      r.substitutes.forEach(function (id) {
        var x = state.rows.filter(function (y) { return y.id === id; })[0];
        if (!x) return;
        h += '<span class="sub-chip ' + (x.inStock ? "" : "off") + '" title="' + (x.inStock ? "" : "Этой замены сейчас тоже нет") + '">' + esc(x.name) +
          '<button type="button" aria-label="Убрать замену ' + esc(x.name) + '"' +
          on("click", function () { patch(k, { substitutes: r.substitutes.filter(function (s) { return s !== id; }) }); }) + ">×</button></span>";
      });
      h += '<select aria-label="Добавить замену для ' + esc(r.name) + '"' +
        on("change", function (e) { if (e.target.value) patch(k, { substitutes: r.substitutes.concat([Number(e.target.value)]) }); }) + ">";
      h += '<option value="">' + (r.substitutes.length ? "+ ещё" : "+ указать") + "</option>";
      state.rows.forEach(function (y) {
        if (y.id && y.key !== k && r.substitutes.indexOf(y.id) < 0) h += '<option value="' + y.id + '">' + esc(y.name) + "</option>";
      });
      h += "</select></div></td>";

      h += '<td><div class="qty"><button type="button"' + on("click", function () { bump(k, -50); }) + ">−</button>" +
        '<input class="num" style="border: 0; width: 70px; text-align: center" data-k="price-' + esc(k) + '" value="' + esc(r.priceText) + '" inputmode="decimal"' +
        on("input", function (e) { patch(k, { priceText: e.target.value }); }) + ">" +
        '<button type="button"' + on("click", function () { bump(k, 50); }) + ">+</button></div></td>";
      h += '<td class="small muted">' + (r.prevPriceKop !== null ? U.kopToInput(r.prevPriceKop) : "—") + "</td>";
      h += '<td><button type="button" class="chip ' + (r.inStock ? "on" : "") + '" style="min-height: 34px; padding: 4px 12px"' +
        on("click", function () { patch(k, { inStock: !r.inStock }); }) + ">" + (r.inStock ? "Есть" : "Нет") + "</button></td>";
      h += "<td>" + (r.id ? usage[r.id] || 0 : 0) + "</td>";
      h += '<td><button type="button" class="btn-ghost" aria-label="Удалить цветок"' + on("click", function () { remove(r); }) + ">×</button></td>";
      h += "</tr>";
    });
    h += "</tbody></table></div>";

    h += '<div class="card stack" style="gap: 10px; position: sticky; top: 16px"><span class="label" style="font-size: 18px">Что изменится на сайте</span>';
    h += real.length === 0
      ? '<span class="small muted">Пока ничего. Поменяйте цену или наличие слева.</span>'
      : '<span class="small muted">После сохранения изменится ' + real.length + " " + plural(real.length) + ".</span>";
    changes.forEach(function (c) {
      var was = c.was;
      var now = c.now;
      h += '<div class="sum-row small" style="align-items: baseline"><span style="font-weight: 700">' + esc(c.b.name) + "</span>";
      if (!now.vis.visible && now.vis.reason === "no-flowers") {
        h += '<span class="pill warn">' + (was.vis.visible ? "Скроется" : "Скрыт") + ": нет " + esc(now.vis.missing.join(", ").toLowerCase()) + "</span>";
      } else if (!was.vis.visible && now.vis.visible) {
        h += '<span class="pill ok">Вернётся на сайт</span>';
      } else {
        h += "<span>" + (was.price !== null ? U.formatByn(was.price) : "—") + " → <b>" + (now.price !== null ? U.formatByn(now.price) : "—") + "</b> ";
        if (was.price !== null && now.price !== null) {
          h += '<span style="color: ' + (now.price > was.price ? "var(--danger)" : "var(--olive)") + '">' + (now.price > was.price ? "+" : "−") +
            U.formatByn(Math.abs(now.price - was.price)).replace(" BYN", "") + "</span>";
        }
        h += "</span>";
      }
      h += "</div>";
    });
    h += U.msgHtml(state.msg);
    h += '<button type="button" class="btn btn-lg"' + U.attr("disabled", state.pending) + on("click", save) + ">" + (state.pending ? "Сохраняем…" : "Сохранить и обновить сайт") + "</button>";
    h += '<span class="small muted">Цена букета = цветы по цене из этой таблицы × количество + упаковка. Если цветка нет в наличии и нет замены в наличии, букет скрывается с сайта и вернётся сам, когда цветок появится. С заменой букет остаётся по прежней цене, а в составе пишем «статица (вместо: вероника)».</span>';
    h += "</div></div>";
    return h;
  }

  var ui = U.mount(document.getElementById("admin-app"), view);
})();
