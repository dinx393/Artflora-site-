// Карта зон доставки для покупателя: app/views/partials/zones-map.php. Расчёт — delivery-zones.js (как в админке).
// Leaflet подгружаем при первом открытии карты, чтобы корзина открывалась быстро.
(function () {
  var Z = window.DeliveryZones;
  document.querySelectorAll("[data-zmap]").forEach(function (box) {
    var data = JSON.parse(box.querySelector("[data-zmap-config]").textContent);
    var cfg = data.delivery;
    var toggle = box.querySelector("[data-zmap-toggle]");
    var body = box.querySelector("[data-zmap-body]");
    var started = false;

    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
    }
    function byn(kop) {
      var k = Math.round(kop) % 100;
      return String(Math.floor(Math.round(kop) / 100)) + (k ? "," + (k < 10 ? "0" : "") + k : "") + " BYN";
    }
    // Список районов в корзине: выбрать район = поменять значение и сообщить корзине
    function districtSelect() {
      return document.querySelector("[data-co-district]");
    }
    function choose(name) {
      var sel = districtSelect();
      if (!sel) return;
      sel.value = name;
      sel.dispatchEvent(new Event("change"));
    }

    function legend() {
      var last = cfg.zones.length - 1;
      var h = "";
      var chip = function (color, text, price) {
        return '<span class="zmap-chip"><i style="background: ' + esc(color) + '"></i>' + esc(text) + " · <b>" + esc(price) + "</b></span>";
      };
      cfg.zones.forEach(function (z, i) {
        h += chip(Z.zoneColor(i + 1, z.color), i === last ? "дальше, до МКАД" : "до " + z.maxKm + " км от магазина", byn(z.priceKop));
      });
      h += chip(cfg.outsideColor || Z.OUTSIDE_COLOR, "за МКАД", byn((cfg.zones[last] || {}).priceKop || 0) + " + " + byn(cfg.outsideMkadPerKmKop) + "/км");
      cfg.areas.forEach(function (a) {
        if (!a.noDelivery) h += chip(a.color, a.name, byn(a.priceKop));
      });
      box.querySelector("[data-zmap-legend]").innerHTML = h;
    }

    // Что показать по нажатию на карту
    function popupHtml(point) {
      var q = Z.quote(point, 0, cfg);
      if (!q.ok) return "<b>Сюда не возим</b><br>Напишите нам, может быть, договоримся.";
      var color = Z.quoteColor(q, cfg.zones, cfg.outsideColor);
      var where = q.area ? q.area.name : q.outsideMkadKm ? "за МКАД, " + q.outsideMkadKm + " км" : "по Минску";
      var h = '<span class="zmap-dot" style="background: ' + esc(color) + '"></span><b>Возим сюда · ' + esc(byn(q.priceKop)) + "</b><br>" + esc(where);
      if (!q.area && !q.outsideMkadKm) h += "<br>Бесплатно при заказе от " + esc(byn(cfg.freeFromKop));
      // Подсказываем, что выбрать в списке: свой участок или ближайший район с той же ценой
      if (districtSelect()) {
        var pick = null;
        if (q.area) pick = data.districts.filter(function (d) { return d.isArea && d.name === q.area.name; })[0] || null;
        if (!pick) {
          var best = Infinity;
          data.districts.forEach(function (d) {
            if (d.isArea) return;
            var dq = Z.quote(d, 0, cfg);
            if (!dq.ok || dq.priceKop !== q.priceKop) return;
            var km = Z.distanceKm(point, d);
            if (km < best) { best = km; pick = d; }
          });
        }
        if (pick) h += '<br><button type="button" class="zmap-pick" data-zmap-pick="' + esc(pick.name) + '">Выбрать «' + esc(pick.name) + "»</button>";
      }
      return h;
    }

    function start() {
      started = true;
      legend();
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
      var map = L.map(el, { scrollWheelZoom: false, zoomSnap: 0.25 });
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(map);
      // Показываем всё, куда возим: круг «до N км за МКАД» и свои участки
      var c = cfg.mkadCenter;
      var dLat = (cfg.mkadRadiusKm + cfg.maxOutsideMkadKm) / 111;
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
      if (districtSelect()) {
        data.districts.forEach(function (d) {
          if (d.isArea) return;
          var q = Z.quote(d, 0, cfg);
          var mk = L.marker([d.lat, d.lng], {
            icon: L.divIcon({ className: "map-district", html: '<i style="background:' + Z.quoteColor(q, cfg.zones, cfg.outsideColor) + '"></i><span>' + esc(d.name) + "</span>", iconSize: [14, 14], iconAnchor: [7, 7] }),
            title: d.name,
          }).addTo(map);
          mk.on("click", function () { choose(d.name); map.closePopup(); });
        });
      }
      // Подписи районов — только вблизи, иначе они налезают друг на друга
      function labels() { el.classList.toggle("zmap-far", map.getZoom() < 11); }
      map.on("zoomend", labels);
      labels();

      map.on("click", function (e) {
        L.popup({ closeButton: false, className: "zmap-popup" }).setLatLng(e.latlng).setContent(popupHtml({ lat: e.latlng.lat, lng: e.latlng.lng })).openOn(map);
      });
      el.addEventListener("click", function (e) {
        var b = e.target.closest("[data-zmap-pick]");
        if (!b) return;
        choose(b.getAttribute("data-zmap-pick"));
        map.closePopup();
      });
    }

    toggle.addEventListener("click", function () {
      var open = body.hidden;
      body.hidden = !open;
      toggle.setAttribute("aria-expanded", String(open));
      if (open && !started) start();
    });
    if (!body.hidden) start();
  });
})();
