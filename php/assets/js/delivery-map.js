// Карта зон доставки для покупателя: app/views/partials/zones-map.php. Расчёт — delivery-zones.js (как в админке).
// В корзине (pick-mode) карта и кнопки зон — это и есть выбор, куда везти: выбранное место уходит в корзину
// событием «zmap:place» (detail = { type: "point", lat, lng } | { type: "zone", zone } | { type: "area", name }).
// На странице «Доставка и оплата» карта только показывает зоны и цены.
// Leaflet подгружаем при первом открытии карты, чтобы страница открывалась быстро.
(function () {
  var Z = window.DeliveryZones;
  document.querySelectorAll("[data-zmap]").forEach(function (box) {
    var data = JSON.parse(box.querySelector("[data-zmap-config]").textContent);
    var cfg = data.delivery;
    var pickMode = box.hasAttribute("data-zmap-pick-mode");
    var toggle = box.querySelector("[data-zmap-toggle]");
    var body = box.querySelector("[data-zmap-body]");
    var started = false;
    var map = null;
    var pin = null;
    var selected = null; // ключ выбранной кнопки: "zone:1", "outside", "area:Имя"
    var subtotalKop = 0; // сумма букетов в корзине (событие «zmap:subtotal» от checkout.js): от неё бесплатная доставка

    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
    }
    function byn(kop) {
      var k = Math.round(kop) % 100;
      return String(Math.floor(Math.round(kop) / 100)) + (k ? "," + (k < 10 ? "0" : "") + k : "") + " BYN";
    }

    // Зоны с подписями и ценами: и для кнопок, и для подписей под картой.
    // Бесплатно — как Delivery::quotePlace: только зоны по Минску (до МКАД); за МКАД и участки всегда платные.
    function places() {
      var last = cfg.zones.length - 1;
      var free = subtotalKop >= cfg.freeFromKop;
      var list = cfg.zones.map(function (z, i) {
        return { key: "zone:" + (i + 1), place: { type: "zone", zone: i + 1 }, color: Z.zoneColor(i + 1, z.color), title: i === last ? "Дальше, до МКАД" : "До " + z.maxKm + " км от магазина", price: free ? "Бесплатно" : byn(z.priceKop), free: free };
      });
      if (cfg.otherShops) {
        // Карта «доставка из филиала»: за МКАД цена зависит от того, к какому филиалу ближе
        if (cfg.zones.length) list[list.length - 1].title = "Дальше по Минску";
        list.push({ key: "outside", place: { type: "outside" }, color: cfg.outsideColor || Z.OUTSIDE_COLOR, title: "За МКАД рядом с этим филиалом", price: byn(cfg.outsideMkadKop) });
        list.push({ key: "outside-far", place: { type: "outside" }, color: cfg.shopOrder.farOutsideColor, title: "За МКАД со стороны другого филиала", price: byn(cfg.outsideMkadKop + cfg.shopOrder.outsideExtraKop) });
      } else {
        list.push({ key: "outside", place: { type: "outside" }, color: cfg.outsideColor || Z.OUTSIDE_COLOR, title: "За МКАД", price: byn(cfg.outsideMkadKop) });
      }
      cfg.areas.forEach(function (a) {
        if (!a.noDelivery && a.points.length >= 3) list.push({ key: "area:" + a.name, place: { type: "area", name: a.name }, color: a.color, title: a.name, price: byn(a.priceKop) });
      });
      return list;
    }
    // Какая кнопка соответствует точке на карте
    function keyOf(q) {
      if (!q.ok) return null;
      if (q.area) return "area:" + q.area.name;
      if (q.outside) return "outside";
      return "zone:" + q.zone;
    }

    function legend() {
      box.querySelector("[data-zmap-legend]").innerHTML = places().map(function (p) {
        return '<span class="zmap-chip"><i style="background: ' + esc(p.color) + '"></i>' + esc(p.title) + " · <b>" + esc(p.price) + "</b></span>";
      }).join("");
    }

    // ---- Выбор в корзине ----
    var tip = box.querySelector("[data-zmap-tip]");
    function say(text) {
      if (!tip) return;
      tip.hidden = !text;
      tip.textContent = text || "";
    }
    function renderPlaces() {
      var wrap = box.querySelector("[data-zmap-places]");
      wrap.innerHTML = places().map(function (p) {
        return '<button type="button" class="zmap-place' + (selected === p.key ? " on" : "") + '" data-zmap-key="' + esc(p.key) + '" aria-pressed="' + (selected === p.key) + '">' +
          '<i style="background: ' + esc(p.color) + '"></i><span>' + esc(p.title) + "</span><b" + (p.free ? ' class="gift"' : "") + ">" + esc(p.price) + "</b></button>";
      }).join("");
    }
    function choose(place, key) {
      selected = key;
      renderPlaces();
      document.dispatchEvent(new CustomEvent("zmap:place", { detail: place }));
    }
    function dropPin() {
      if (pin) pin.remove();
      pin = null;
    }
    function pickPoint(latlng) {
      var point = { lat: Math.round(latlng.lat * 100000) / 100000, lng: Math.round(latlng.lng * 100000) / 100000 };
      var q = Z.quote(point, subtotalKop, cfg);
      var L = window.L;
      if (!q.ok) {
        L.popup({ closeButton: false, className: "zmap-popup" }).setLatLng(latlng).setContent("<b>Сюда не возим</b><br>Напишите нам, может быть, договоримся.").openOn(map);
        return;
      }
      dropPin();
      pin = L.marker(latlng, { icon: L.divIcon({ className: "zmap-pin", iconSize: [22, 22], iconAnchor: [11, 11] }), interactive: false, zIndexOffset: 2000 }).addTo(map);
      var where = q.area ? q.area.name : q.outside ? "за МКАД" : "по Минску";
      L.popup({ closeButton: false, className: "zmap-popup", offset: [0, -8] }).setLatLng(latlng)
        .setContent('<span class="zmap-dot" style="background: ' + esc(Z.quoteColor(q, cfg.zones, cfg.outsideColor, cfg.shopOrder && cfg.shopOrder.farOutsideColor)) + '"></span><b>Везём сюда · ' + esc(q.priceKop === 0 ? "бесплатно" : byn(q.priceKop)) + "</b><br>" + esc(where))
        .openOn(map);
      say("");
      choose({ type: "point", lat: point.lat, lng: point.lng }, keyOf(q));
    }
    if (pickMode) {
      renderPlaces();
      document.addEventListener("zmap:subtotal", function (e) {
        if (e.detail === subtotalKop) return;
        subtotalKop = e.detail;
        renderPlaces();
      });
      box.querySelector("[data-zmap-places]").addEventListener("click", function (e) {
        var b = e.target.closest("[data-zmap-key]");
        if (!b) return;
        var key = b.getAttribute("data-zmap-key");
        var p = places().filter(function (x) { return x.key === key; })[0];
        say("");
        dropPin();
        if (map) map.closePopup();
        choose(p.place, key);
      });
    }

    function start() {
      started = true;
      if (!pickMode) legend();
      var css = document.createElement("link");
      css.rel = "stylesheet";
      css.href = data.leaflet.css;
      document.head.appendChild(css);
      var js = document.createElement("script");
      js.src = data.leaflet.js;
      js.onload = draw;
      document.head.appendChild(js);
    }

    function draw() {
      var L = window.L;
      var el = box.querySelector("[data-zmap-map]");
      map = L.map(el, { scrollWheelZoom: false, zoomSnap: 0.25 });
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(map);
      // Показываем Минск с запасом за МКАД и свои участки
      var c = cfg.mkadCenter;
      var dLat = (cfg.mkadRadiusKm + 5) / 111;
      var dLng = dLat / Math.cos((c.lat * Math.PI) / 180);
      var bounds = L.latLngBounds([c.lat - dLat, c.lng - dLng], [c.lat + dLat, c.lng + dLng]);
      cfg.areas.forEach(function (a) {
        if (!a.noDelivery) a.points.forEach(function (p) { bounds.extend([p.lat, p.lng]); });
      });
      map.fitBounds(bounds, { padding: [8, 8] });
      Z.zonesLayer(L, map, cfg).addTo(map);

      L.circle([c.lat, c.lng], { radius: cfg.mkadRadiusKm * 1000, color: "#7E1F3C", weight: 1.5, dashArray: "6 6", fill: false, interactive: false }).addTo(map);
      cfg.areas.forEach(function (a) {
        if (a.points.length < 3) return;
        L.polygon(a.points.map(function (p) { return [p.lat, p.lng]; }), {
          color: a.color, weight: 2, dashArray: a.noDelivery ? "6 4" : undefined, fillOpacity: 0, interactive: false,
        }).addTo(map);
      });
      cfg.shops.forEach(function (s) {
        L.marker([s.location.lat, s.location.lng], {
          icon: L.divIcon({ className: "map-shop", html: "<span>⌂</span><b>" + esc(s.name) + "</b>", iconSize: [34, 34], iconAnchor: [17, 17] }),
          interactive: false, zIndexOffset: 1000,
        }).addTo(map);
      });
      // Названия районов — только чтобы легче найти себя; нажатие проходит сквозь них на карту
      data.districts.forEach(function (d) {
        if (d.isArea) return;
        L.marker([d.lat, d.lng], {
          icon: L.divIcon({ className: "map-district zmap-name", html: "<span>" + esc(d.name) + "</span>", iconSize: [0, 0] }),
          interactive: false,
        }).addTo(map);
      });
      function labels() { el.classList.toggle("zmap-far", map.getZoom() < 11); }
      map.on("zoomend", labels);
      labels();

      map.on("click", function (e) {
        if (pickMode) return pickPoint(e.latlng);
        var q = Z.quote({ lat: e.latlng.lat, lng: e.latlng.lng }, 0, cfg);
        var freeKop = !q.area && !q.outside ? cfg.zones[q.zone - 1].freeKop || 0 : 0;
        var html = !q.ok ? "<b>Сюда не возим</b><br>Напишите нам, может быть, договоримся." :
          '<span class="zmap-dot" style="background: ' + esc(Z.quoteColor(q, cfg.zones, cfg.outsideColor, cfg.shopOrder && cfg.shopOrder.farOutsideColor)) + '"></span><b>Возим сюда · ' + esc(byn(q.priceKop)) + "</b><br>" +
          esc(q.area ? q.area.name : q.outside ? (q.farSide ? "за МКАД, со стороны другого филиала" : "за МКАД") : "по Минску") +
          (!q.area && !q.outside ? "<br>При заказе от " + esc(byn(cfg.freeFromKop)) + (freeKop ? " — " + esc(byn(freeKop)) : " — бесплатно") : "");
        L.popup({ closeButton: false, className: "zmap-popup" }).setLatLng(e.latlng).setContent(html).openOn(map);
      });
    }

    if (toggle) {
      toggle.addEventListener("click", function () {
        var open = body.hidden;
        body.hidden = !open;
        toggle.setAttribute("aria-expanded", String(open));
        if (open && !started) start();
      });
    }
    // Карта может быть скрыта (самовывоз, другой шаг на телефоне): Leaflet надо пересчитать размер, когда её покажут
    if (!body.hidden) start();
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function () { if (map) map.invalidateSize(); }).observe(box);
    }
  });
})();
