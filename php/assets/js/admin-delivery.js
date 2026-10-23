// Раздел «Доставка» = src/app/admin/(panel)/delivery/DeliveryEditor.tsx + DeliveryMap.tsx
// Слева зоны, свои участки, районы и магазины, справа карта. Цвета на карте = зоны.
// Районы и магазины перетаскиваются прямо на карте.
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var formatByn = U.formatByn;
  var data = U.json("admin-data");

  // ---------- расчёт: assets/js/delivery-zones.js ----------
  var Z = window.DeliveryZones;
  var OUTSIDE_COLOR = Z.OUTSIDE_COLOR;
  var zoneColor = Z.zoneColor;
  var nextColor = Z.nextColor;
  var quoteColor = Z.quoteColor;
  var quote = Z.quote;
  var rgba = Z.rgba;

  // ---------- состояние ----------
  var state = {
    cfg: data.delivery,
    districts: data.districts,
    selected: null,
    selectedArea: null,
    draft: null,
    msg: null,
    dirty: false,
    pending: false,
    texts: {}, // текст полей «BYN», пока набирают
  };
  if (!state.cfg.areas) state.cfg.areas = [];

  function changeCfg(next) {
    state.cfg = next;
    state.dirty = true;
    state.msg = null;
    render();
  }
  function changeDistricts(next) {
    state.districts = next;
    state.dirty = true;
    state.msg = null;
    render();
  }
  function withCfg(p) {
    return Object.assign({}, state.cfg, p);
  }
  function setZone(i, p) {
    changeCfg(withCfg({ zones: state.cfg.zones.map(function (z, j) { return j === i ? Object.assign({}, z, p) : z; }) }));
  }
  function setAreas(next) {
    changeCfg(withCfg({ areas: next }));
  }
  function setArea(i, p) {
    setAreas(state.cfg.areas.map(function (a, j) { return j === i ? Object.assign({}, a, p) : a; }));
  }
  function usedColors() {
    var cfg = state.cfg;
    return cfg.zones.map(function (z, i) { return zoneColor(i + 1, z.color); })
      .concat(cfg.areas.map(function (a) { return a.color; }))
      .concat([cfg.outsideColor || OUTSIDE_COLOR]);
  }

  function finishDraft() {
    var draft = state.draft;
    var areas = state.cfg.areas;
    if (!draft || draft.length < 3) return;
    var n = areas.length + 1;
    while (areas.some(function (a) { return a.name === "Участок " + n; })) n++;
    state.selectedArea = areas.length;
    state.draft = null;
    setAreas(areas.concat([{ name: "Участок " + n, color: nextColor(usedColors()), priceKop: 3000, noDelivery: false, points: draft }]));
    syncDrawMode();
  }

  function save() {
    state.pending = true;
    render();
    U.post("/admin/delivery/save", { delivery: state.cfg, districts: state.districts }).then(function (r) {
      state.pending = false;
      state.msg = r.ok ? { ok: true, text: "Сохранено, на сайте уже новые цены" } : { ok: false, text: r.error || "Ошибка" };
      if (r.ok) state.dirty = false;
      render();
    });
  }

  function select(i) {
    state.selected = i;
    render();
    var row = document.querySelector('#dl-left [data-i="' + i + '"]');
    if (row) row.scrollIntoView({ block: "nearest", behavior: "smooth" });
    panToDistrict();
  }

  // Поле суммы в BYN (хранится в копейках): пока набирают, держим текст
  function money(on, key, kop, onChange) {
    var text = state.texts[key] !== undefined ? state.texts[key] : U.kopToInput(kop);
    return '<input class="num" inputmode="decimal" data-k="' + key + '" value="' + esc(text) + '"' + on("input", function (e) {
      state.texts[key] = e.target.value;
      var k = U.parseByn(e.target.value);
      if (k !== null) onChange(k);
    }) + ">";
  }
  function num(on, key, value, step, width, onChange) {
    return '<input class="num" style="width: ' + width + 'px" type="number" step="' + step + '" data-k="' + key + '" value="' + (isFinite(value) ? value : "") + '"' +
      on("input", function (e) { onChange(parseFloat(e.target.value)); }) + ">";
  }

  // ---------- шапка ----------
  function headView(on) {
    var h = '<div class="stack" style="gap: 4px"><h1>Доставка</h1><span class="muted">Цвет на карте — зона доставки. Районы и магазины можно перетаскивать прямо на карте.</span></div>';
    h += '<div class="row" style="gap: 12px">';
    if (state.msg) h += '<span class="' + (state.msg.ok ? "pill ok" : "error") + '">' + esc(state.msg.text) + "</span>";
    if (state.dirty && !state.msg) h += '<span class="small muted">Есть несохранённые изменения</span>';
    h += '<button type="button" class="btn"' + U.attr("disabled", state.pending) + on("click", save) + ">" + (state.pending ? "Сохраняем…" : "Сохранить") + "</button></div>";
    return h;
  }

  // ---------- левая колонка ----------
  function leftView(on) {
    var cfg = state.cfg;
    var areas = cfg.areas;
    var districts = state.districts;
    var last = cfg.zones.length - 1;
    var lastKm = (cfg.zones[last] || {}).maxKm || 0;
    var h = "";

    // Зоны
    h += '<div class="card stack" style="gap: 10px"><span class="label" style="font-size: 18px">Зоны</span><span class="small muted">Расстояние считаем от ближайшего магазина по прямой.</span>';
    cfg.zones.forEach(function (z, i) {
      h += '<div class="zone-edit"><input type="color" class="color-pick sm" value="' + zoneColor(i + 1, z.color) + '" aria-label="Цвет зоны ' + (i + 1) + '"' +
        on("input", function (e) { setZone(i, { color: e.target.value }); }) + "><b>Зона " + (i + 1) + '</b><span class="small muted">' + (i === last ? "дальше, до МКАД" : "до") + "</span>";
      h += i === last ? "<span></span>" : '<span class="row" style="gap: 4px">' + num(on, "zone-km-" + i, z.maxKm, 0.5, 70, function (n) { setZone(i, { maxKm: n }); }) + " км</span>";
      h += '<span class="row" style="gap: 4px">' + money(on, "zone-price-" + i, z.priceKop, function (k) { setZone(i, { priceKop: k }); }) + " BYN</span>";
      h += '<button type="button" class="btn-ghost"' + U.attr("disabled", cfg.zones.length === 1) + ' aria-label="Убрать зону"' + on("click", function () {
        state.texts = {};
        changeCfg(withCfg({
          zones: cfg.zones.map(function (x, j) { return Object.assign({}, x, { color: zoneColor(j + 1, x.color) }); }).filter(function (_, j) { return j !== i; }),
        }));
      }) + ">×</button></div>";
    });
    h += '<button type="button" class="btn-ghost small" style="align-self: flex-start"' + on("click", function () {
      // новая зона встаёт перед последней («до МКАД»)
      var prev = (cfg.zones[last - 1] || {}).maxKm || 0;
      var zone = { maxKm: Math.round((prev + Math.max(1, (lastKm - prev) / 2)) * 2) / 2, priceKop: cfg.zones[last].priceKop, color: nextColor(usedColors()) };
      // у старых зон фиксируем их цвет, чтобы он не сдвинулся с номером
      var own = cfg.zones.map(function (x, j) { return Object.assign({}, x, { color: zoneColor(j + 1, x.color) }); });
      state.texts = {};
      changeCfg(withCfg({ zones: own.slice(0, last).concat([zone, own[last]]) }));
    }) + ">+ Добавить зону</button>";
    h += '<div class="zone-edit"><input type="color" class="color-pick sm" value="' + esc(cfg.outsideColor || OUTSIDE_COLOR) + '" aria-label="Цвет «за МКАД»"' +
      on("input", function (e) { changeCfg(withCfg({ outsideColor: e.target.value })); }) + '><b>За МКАД</b><span class="small muted">+ за км</span>' +
      '<span class="row" style="gap: 4px">' + money(on, "outside-km", cfg.outsideMkadPerKmKop, function (k) { changeCfg(withCfg({ outsideMkadPerKmKop: k })); }) + " BYN</span>" +
      '<span class="row small" style="gap: 4px; grid-column: 3 / -1">не дальше ' + num(on, "max-outside", cfg.maxOutsideMkadKm, 1, 60, function (n) { changeCfg(withCfg({ maxOutsideMkadKm: n })); }) + " км</span></div>";
    h += '<span class="small muted">За МКАД: цена зоны ' + cfg.zones.length + " (" + formatByn((cfg.zones[last] || {}).priceKop || 0) + ") + " + formatByn(cfg.outsideMkadPerKmKop) + " за каждый км от МКАД.</span>";
    h += '<div class="stack" style="gap: 8px; margin-top: 6px">';
    h += '<label class="sum-row" style="justify-content: flex-start; gap: 12px"><span>Бесплатно от, BYN</span>' + money(on, "free-from", cfg.freeFromKop, function (k) { changeCfg(withCfg({ freeFromKop: k })); }) + "</label>";
    h += '<label class="sum-row" style="justify-content: flex-start; gap: 12px"><span>Скидка за самовывоз, %</span>' + num(on, "pickup", cfg.pickupDiscountPct, 1, 70, function (n) { changeCfg(withCfg({ pickupDiscountPct: n })); }) + "</label>";
    h += '<label class="sum-row" style="justify-content: flex-start; gap: 12px"><span>Радиус МКАД, км</span>' + num(on, "mkad", cfg.mkadRadiusKm, 0.1, 70, function (n) { changeCfg(withCfg({ mkadRadiusKm: n })); }) + "</label>";
    h += "</div></div>";

    // Свои участки
    h += '<div class="card stack" style="gap: 10px"><div class="row between"><span class="label" style="font-size: 18px">Свои участки</span>';
    if (!state.draft) {
      h += '<button type="button" class="btn-ghost small"' + on("click", function () { state.draft = []; state.selectedArea = null; render(); syncDrawMode(); }) + ">+ Нарисовать участок</button>";
    }
    h += "</div>";
    h += '<span class="small muted">Участок важнее кругов: внутри него своя цена или «не возим». Например, «Смолевичи — 35 BYN» или «Радошковичи — не возим». Бесплатная доставка от суммы на участки не действует. Участок с ценой покупатель видит на карте в корзине и может выбрать в списке районов по его названию.</span>';
    if (state.draft) {
      var d = state.draft;
      h += '<div class="zone draw-hint"><b>Кликайте по карте по краю участка, точка за точкой</b><span class="small muted">Поставлено точек: ' + d.length + ". Замкнуть не нужно, последняя соединится с первой.</span>" +
        '<div class="row" style="gap: 8px"><button type="button" class="btn"' + U.attr("disabled", d.length < 3) + on("click", finishDraft) + ">Готово</button>" +
        '<button type="button" class="btn btn-outline"' + U.attr("disabled", d.length === 0) + on("click", function () { state.draft = d.slice(0, -1); render(); }) + ">Убрать последнюю</button>" +
        '<button type="button" class="btn-ghost"' + on("click", function () { state.draft = null; render(); syncDrawMode(); }) + ">Отмена</button></div></div>";
    }
    areas.forEach(function (a, i) {
      h += '<div class="area-row ' + (state.selectedArea === i ? "on" : "") + '"' + on("click", function () { if (!state.draft && state.selectedArea !== i) { state.selectedArea = i; render(); fitArea(); } }) + ">";
      h += '<input type="color" class="color-pick sm" value="' + esc(a.color) + '" aria-label="Цвет участка ' + esc(a.name) + '"' + on("input", function (e) { setArea(i, { color: e.target.value }); }) + ">";
      h += '<input class="input" maxlength="40" data-k="area-' + i + '" value="' + esc(a.name) + '"' +
        on("focusin", function () { if (state.selectedArea !== i) { state.selectedArea = i; render(); fitArea(); } }) +
        on("input", function (e) { setArea(i, { name: e.target.value }); }) + ">";
      h += a.noDelivery ? '<span class="small muted">—</span>' : '<span class="row" style="gap: 4px">' + money(on, "area-price-" + i, a.priceKop, function (k) { setArea(i, { priceKop: k }); }) + " BYN</span>";
      h += '<label class="check small" style="gap: 6px"><input type="checkbox"' + U.attr("checked", a.noDelivery) + on("change", function (e) { setArea(i, { noDelivery: e.target.checked }); }) + "> не возим</label>";
      h += '<button type="button" class="btn-ghost" aria-label="Убрать участок ' + esc(a.name) + '"' + on("click", function (e) {
        e.stopPropagation();
        state.selectedArea = null;
        state.texts = {};
        setAreas(areas.filter(function (_, j) { return j !== i; }));
      }) + ">×</button></div>";
    });
    if (state.selectedArea !== null && areas[state.selectedArea] && !state.draft) {
      h += '<span class="small muted">«' + esc(areas[state.selectedArea].name) + "»: тяните белые точки на карте. Полупрозрачные точки посередине сторон добавляют новую, двойной клик по точке её убирает.</span>";
    }
    h += "</div>";

    // Районы
    h += '<div class="card stack" style="gap: 10px"><div class="row between"><span class="label" style="font-size: 18px">Районы · ' + districts.length + "</span>";
    h += '<button type="button" class="btn-ghost small"' + on("click", function () {
      var c = mapCenter();
      var n = 1;
      while (districts.some(function (x) { return x.name === "Новый район " + n; })) n++;
      state.selected = districts.length;
      changeDistricts(districts.concat([{ name: "Новый район " + n, lat: c.lat, lng: c.lng }]));
    }) + ">+ Добавить в центр карты</button></div>";
    h += '<span class="small muted">Покупатель выбирает район в корзине, цена — по точке района. Нажмите на район, чтобы найти его на карте.</span>';
    h += '<div class="district-list">';
    districts.forEach(function (x, i) {
      var q = quote(x, 0, cfg);
      h += '<div data-i="' + i + '" class="district-row ' + (state.selected === i ? "on" : "") + '"' + on("click", function () { if (state.selected !== i) { state.selected = i; render(); panToDistrict(); } }) + ">";
      h += '<i class="zone-dot" style="background: ' + quoteColor(q, cfg.zones, cfg.outsideColor) + '"></i>';
      h += '<input class="input" maxlength="40" data-k="district-' + i + '" value="' + esc(x.name) + '"' +
        on("focusin", function () { if (state.selected !== i) { state.selected = i; render(); panToDistrict(); } }) +
        on("input", function (e) {
          var v = e.target.value;
          changeDistricts(districts.map(function (y, j) { return j === i ? Object.assign({}, y, { name: v }) : y; }));
        }) + ">";
      h += '<span class="small" style="white-space: nowrap">';
      if (q.ok) h += "<b>" + formatByn(q.priceKop) + '</b> <span class="muted">' + esc(q.area ? q.area.name : q.outsideMkadKm > 0 ? "за МКАД " + q.outsideMkadKm + " км" : "зона " + q.zone) + "</span>";
      else h += '<span class="error" style="padding: 0">' + esc(q.area ? "не возим · " + q.area.name : "слишком далеко") + "</span>";
      h += "</span>";
      h += '<button type="button" class="btn-ghost" aria-label="Убрать район ' + esc(x.name) + '"' + on("click", function (e) {
        e.stopPropagation();
        state.selected = null;
        changeDistricts(districts.filter(function (_, j) { return j !== i; }));
      }) + ">×</button></div>";
    });
    h += "</div></div>";

    // Магазины
    h += '<div class="card stack" style="gap: 10px"><span class="label" style="font-size: 18px">Магазины</span><span class="small muted">Доставка едет из ближайшего. Точку магазина можно передвинуть на карте (значок ⌂).</span>';
    cfg.shops.forEach(function (s, i) {
      function set(p) {
        changeCfg(withCfg({ shops: cfg.shops.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; }) }));
      }
      h += '<div class="shop-edit">' +
        '<input class="input" aria-label="Название" data-k="shop-name-' + i + '" value="' + esc(s.name) + '"' + on("input", function (e) { set({ name: e.target.value }); }) + ">" +
        '<input class="input" aria-label="Адрес" data-k="shop-address-' + i + '" value="' + esc(s.address) + '"' + on("input", function (e) { set({ address: e.target.value }); }) + ">" +
        '<input class="input" aria-label="Часы работы" data-k="shop-hours-' + i + '" value="' + esc(s.hours) + '"' + on("input", function (e) { set({ hours: e.target.value }); }) + ">" +
        "</div>";
    });
    h += "</div>";
    return h;
  }

  // ---------- подписи под картой ----------
  function legendView() {
    var cfg = state.cfg;
    var last = cfg.zones.length - 1;
    var h = "";
    cfg.zones.forEach(function (z, i) {
      h += '<span><i class="zone-dot" style="background: ' + zoneColor(i + 1, z.color) + '"></i>' + (i === last ? "дальше, до МКАД" : "до " + z.maxKm + " км") + " · " + formatByn(z.priceKop) + "</span>";
    });
    h += '<span><i class="zone-dot" style="background: ' + esc(cfg.outsideColor || OUTSIDE_COLOR) + '"></i>за МКАД · ' + formatByn((cfg.zones[last] || {}).priceKop || 0) + " + " + formatByn(cfg.outsideMkadPerKmKop) + "/км</span>";
    cfg.areas.forEach(function (a) {
      h += '<span><i class="zone-dot" style="background: ' + esc(a.color) + '"></i>' + esc(a.name) + " · " + (a.noDelivery ? "не возим" : formatByn(a.priceKop)) + "</span>";
    });
    h += '<span class="muted">пунктир — МКАД и предел «дальше не возим»</span>';
    return h;
  }

  // ---------- карта ----------
  // Зоны рисуем тем же расчётом, что и цену в корзине, поэтому цвет на карте всегда совпадает с ценой.
  var L = window.L;
  var box = document.getElementById("dl-map");
  var map = L.map(box, { zoomControl: true, attributionControl: true }).setView([53.905, 27.56], 11);
  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map);
  var marks = L.layerGroup().addTo(map);
  var zonesLayer = null;
  var zonesKey = "";
  map.on("click", function (e) {
    if (state.draft) addDraftPoint(e.latlng);
  });

  function round(p) {
    return { lat: Math.round(p.lat * 10000) / 10000, lng: Math.round(p.lng * 10000) / 10000 };
  }
  function mapCenter() {
    return round(map.getCenter());
  }
  function addDraftPoint(ll) {
    state.draft = state.draft.concat([round(ll)]);
    render();
  }
  // В режиме рисования — курсор-крестик, двойной клик не приближает карту
  function syncDrawMode() {
    box.classList.toggle("drawing", !!state.draft);
    if (state.draft) map.doubleClickZoom.disable();
    else map.doubleClickZoom.enable();
  }
  // Выбранный участок — показываем целиком
  function fitArea() {
    var a = state.selectedArea === null ? null : state.cfg.areas[state.selectedArea];
    if (a && a.points.length >= 3) {
      var b = L.latLngBounds(a.points.map(function (p) { return [p.lat, p.lng]; }));
      if (!map.getBounds().contains(b)) map.fitBounds(b.pad(0.4));
    }
  }
  // Выбранный район — показываем на карте
  function panToDistrict() {
    var d = state.selected === null ? null : state.districts[state.selected];
    if (d && !map.getBounds().pad(-0.1).contains([d.lat, d.lng])) map.panTo([d.lat, d.lng]);
  }

  function drawZones() {
    var cfg = state.cfg;
    // Цветные зоны тяжело считать: перерисовываем, только если поменялись настройки, а не название района
    var key = JSON.stringify(cfg);
    if (key === zonesKey && zonesLayer) return;
    zonesKey = key;
    if (zonesLayer) zonesLayer.remove();
    var layer = Z.zonesLayer(L, map, cfg);
    layer.addTo(map);
    zonesLayer = layer;
  }

  function drawMarks() {
    var cfg = state.cfg;
    var g = marks;
    g.clearLayers();
    // МКАД и предел «дальше не возим» — пунктиром
    L.circle([cfg.mkadCenter.lat, cfg.mkadCenter.lng], { radius: cfg.mkadRadiusKm * 1000, color: "#7E1F3C", weight: 1.5, dashArray: "6 6", fill: false, interactive: false }).addTo(g);
    L.circle([cfg.mkadCenter.lat, cfg.mkadCenter.lng], {
      radius: (cfg.mkadRadiusKm + cfg.maxOutsideMkadKm) * 1000,
      color: "#7E1F3C",
      weight: 1,
      opacity: 0.5,
      dashArray: "2 6",
      fill: false,
      interactive: false,
    }).addTo(g);

    // Свои участки: контур, подпись; у выбранного — точки, которые можно тянуть
    cfg.areas.forEach(function (a, i) {
      if (a.points.length < 3) return;
      var on = state.selectedArea === i;
      var poly = L.polygon(a.points.map(function (p) { return [p.lat, p.lng]; }), {
        color: a.color, weight: on ? 3 : 2, dashArray: a.noDelivery ? "6 4" : undefined, fillOpacity: 0, bubblingMouseEvents: false,
      }).addTo(g);
      poly.bindTooltip(esc(a.name) + " · " + (a.noDelivery ? "не возим" : (a.priceKop / 100).toLocaleString("ru-RU") + " BYN"), { permanent: true, direction: "center", className: "map-area-label" });
      poly.on("click", function (e) {
        if (state.draft) return addDraftPoint(e.latlng);
        state.selectedArea = i;
        render();
        fitArea();
      });
      if (!on || state.draft) return;
      var pts = a.points.slice();
      function commit(next) {
        setArea(i, { points: next });
      }
      pts.forEach(function (p, k) {
        var v = L.marker([p.lat, p.lng], { icon: L.divIcon({ className: "map-vertex", iconSize: [14, 14], iconAnchor: [7, 7] }), draggable: true, zIndexOffset: 1200, title: "Тяните точку. Двойной клик — убрать" }).addTo(g);
        v.on("drag", function () {
          pts[k] = round(v.getLatLng());
          poly.setLatLngs(pts.map(function (q) { return [q.lat, q.lng]; }));
        });
        v.on("dragend", function () { commit(pts); });
        v.on("dblclick", function () {
          if (pts.length > 3) commit(pts.filter(function (_, j) { return j !== k; }));
        });
      });
      // полупрозрачные точки посередине сторон: потянули — появилась новая вершина
      pts.forEach(function (p, k) {
        var n = pts[(k + 1) % pts.length];
        var mid = L.marker([(p.lat + n.lat) / 2, (p.lng + n.lng) / 2], { icon: L.divIcon({ className: "map-vertex mid", iconSize: [12, 12], iconAnchor: [6, 6] }), draggable: true, zIndexOffset: 1100, title: "Потяните, чтобы добавить точку" }).addTo(g);
        mid.on("dragend", function () { commit(pts.slice(0, k + 1).concat([round(mid.getLatLng())], pts.slice(k + 1))); });
      });
    });

    // Участок, который сейчас рисуют
    var draft = state.draft;
    if (draft) {
      var ll = draft.map(function (p) { return [p.lat, p.lng]; });
      if (draft.length > 1) L.polyline(ll, { color: "#7E1F3C", weight: 2, dashArray: "4 4", interactive: false }).addTo(g);
      if (draft.length > 2) L.polygon(ll, { color: "#7E1F3C", weight: 0, fillOpacity: 0.15, interactive: false }).addTo(g);
      draft.forEach(function (p) { L.marker([p.lat, p.lng], { icon: L.divIcon({ className: "map-vertex", iconSize: [14, 14], iconAnchor: [7, 7] }), interactive: false }).addTo(g); });
    }

    cfg.shops.forEach(function (s, i) {
      var icon = L.divIcon({ className: "map-shop", html: "<span>⌂</span><b>" + esc(s.name) + "</b>", iconSize: [34, 34], iconAnchor: [17, 17] });
      var mk = L.marker([s.location.lat, s.location.lng], { icon: icon, draggable: true, zIndexOffset: 1000, title: s.name + ": перетащите, если точка не та" }).addTo(g);
      mk.on("dragend", function () {
        var p = round(mk.getLatLng());
        changeCfg(withCfg({ shops: cfg.shops.map(function (x, j) { return j === i ? Object.assign({}, x, { location: p }) : x; }) }));
      });
    });

    state.districts.forEach(function (d, i) {
      var q = quote(d, 0, cfg);
      var icon = L.divIcon({
        className: "map-district " + (state.selected === i ? "on" : "") + " " + (q.ok ? "" : "far"),
        html: '<i style="background:' + quoteColor(q, cfg.zones, cfg.outsideColor) + '"></i><span>' + esc(d.name) + "</span>",
        iconSize: [14, 14],
        iconAnchor: [7, 7],
      });
      var mk = L.marker([d.lat, d.lng], { icon: icon, draggable: true, zIndexOffset: state.selected === i ? 900 : 0 }).addTo(g);
      mk.on("click", function () { select(i); });
      mk.on("dragend", function () {
        var p = round(mk.getLatLng());
        state.districts = state.districts.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; });
        state.dirty = true;
        state.msg = null;
        select(i);
      });
    });
  }

  var head = U.mount(document.getElementById("dl-head"), headView);
  var left = U.mount(document.getElementById("dl-left"), leftView);
  var legend = document.getElementById("dl-legend");
  function render() {
    head.render();
    left.render();
    legend.innerHTML = legendView();
    drawZones();
    drawMarks();
  }
  render();
})();
