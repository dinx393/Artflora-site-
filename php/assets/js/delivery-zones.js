// Зоны доставки на карте — общее для админки («Доставка») и корзины.
// Расчёт как в app/lib/Delivery.php: цвет точки на карте всегда совпадает с ценой в корзине.
(function () {
  var ZONE_COLORS = ["#4f8ef7", "#f5892b", "#2fb36d", "#2cc6e8", "#ef5d6c", "#f2b705", "#8f6ed5", "#d45fb4", "#7f8c3a", "#5c6bc0"];
  var OUTSIDE_COLOR = "#a98be8";
  function zoneColor(zone, own) {
    return own || ZONE_COLORS[(zone - 1) % ZONE_COLORS.length];
  }
  function nextColor(used) {
    var taken = used.map(function (c) { return c.toLowerCase(); });
    var all = ZONE_COLORS.concat(["#e57373", "#26a69a", "#ffb74d", "#9575cd", "#4db6ac", "#a1887f"]);
    for (var i = 0; i < all.length; i++) if (taken.indexOf(all[i]) < 0) return all[i];
    return "#" + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, "0");
  }
  function quoteColor(q, zones, outsideColor) {
    if (q.area) return q.area.color;
    if (!q.ok) return "#999999";
    if (q.outsideMkadKm) return outsideColor || OUTSIDE_COLOR;
    return zoneColor(q.zone, (zones[q.zone - 1] || {}).color);
  }
  function insidePolygon(p, poly) {
    var inside = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      var a = poly[i];
      var b = poly[j];
      if (a.lat > p.lat !== b.lat > p.lat && p.lng < ((b.lng - a.lng) * (p.lat - a.lat)) / (b.lat - a.lat) + a.lng) inside = !inside;
    }
    return inside;
  }
  function distanceKm(a, b) {
    var toRad = function (d) { return (d * Math.PI) / 180; };
    var dLat = toRad(b.lat - a.lat);
    var dLng = toRad(b.lng - a.lng);
    var h = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.pow(Math.sin(dLng / 2), 2);
    return 2 * 6371 * Math.asin(Math.sqrt(h));
  }
  function quote(point, subtotalKop, cfg) {
    var km = Infinity;
    cfg.shops.forEach(function (s) { km = Math.min(km, distanceKm(point, s.location)); });
    var area = null;
    (cfg.areas || []).some(function (a) { return a.points.length >= 3 && insidePolygon(point, a.points) && (area = a); });
    if (area) {
      var tag = { name: area.name, color: area.color };
      if (area.noDelivery) return { ok: false, km: 0, area: tag };
      return { ok: true, km: Math.round(km * 10) / 10, zone: 0, outsideMkadKm: 0, priceKop: area.priceKop, area: tag };
    }
    var outside = Math.max(0, Math.ceil(distanceKm(point, cfg.mkadCenter) - cfg.mkadRadiusKm));
    if (outside > cfg.maxOutsideMkadKm) return { ok: false, km: outside };
    var last = cfg.zones.length - 1;
    var zi = cfg.zones.findIndex(function (z) { return km <= z.maxKm; });
    if (zi === -1 || outside > 0) zi = last;
    var extra = outside * cfg.outsideMkadPerKmKop;
    var base = cfg.zones[zi].priceKop + extra;
    var free = subtotalKop >= cfg.freeFromKop;
    return { ok: true, km: Math.round(km * 10) / 10, zone: zi + 1, outsideMkadKm: outside, priceKop: free ? extra : base };
  }

  function rgba(hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return "rgba(" + (n >> 16) + ", " + ((n >> 8) & 255) + ", " + (n & 255) + ", " + a + ")";
  }
  // Слой для Leaflet: каждую клеточку 4×4 px красим цветом зоны, в которую попадает эта точка
  function zonesLayer(L, map, cfg) {
    var layer = new L.GridLayer({ opacity: 1, zIndex: 2 });
    layer.createTile = function (coords) {
      var tile = document.createElement("canvas");
      var size = layer.getTileSize();
      tile.width = size.x;
      tile.height = size.y;
      var ctx = tile.getContext("2d");
      var step = 4;
      for (var y = 0; y < size.y; y += step) {
        for (var x = 0; x < size.x; x += step) {
          var ll = map.unproject([coords.x * size.x + x + step / 2, coords.y * size.y + y + step / 2], coords.z);
          var q = quote({ lat: ll.lat, lng: ll.lng }, 0, cfg);
          if (!q.ok && !q.area) continue;
          ctx.fillStyle = rgba(quoteColor(q, cfg.zones, cfg.outsideColor), q.ok && q.outsideMkadKm > 0 ? 0.28 : 0.38);
          ctx.fillRect(x, y, step, step);
        }
      }
      return tile;
    };
    return layer;
  }

  window.DeliveryZones = {
    ZONE_COLORS: ZONE_COLORS, OUTSIDE_COLOR: OUTSIDE_COLOR, zoneColor: zoneColor, nextColor: nextColor, quoteColor: quoteColor,
    insidePolygon: insidePolygon, distanceKm: distanceKm, quote: quote, rgba: rgba, zonesLayer: zonesLayer,
  };
})();
