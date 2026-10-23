<?php
// Карта зон доставки для покупателя (решение владельца 2026-10-23): цвет — цена, как в админке «Доставка».
// Нажатие на карту показывает, возим ли туда и сколько стоит; точка района выбирает его в списке корзины.
// Переменные: $delivery (настройки доставки), $districts (районы с ценами, как в корзине), $open (сразу открыта).
// Оживляет assets/js/delivery-map.js; Leaflet грузится только когда карту открыли.
$zmap = [
    'delivery' => [
        'shops' => array_map(fn ($s) => ['name' => $s['name'], 'location' => $s['location']], $delivery['shops']),
        'zones' => $delivery['zones'], 'mkadCenter' => $delivery['mkadCenter'], 'mkadRadiusKm' => $delivery['mkadRadiusKm'],
        'outsideMkadPerKmKop' => $delivery['outsideMkadPerKmKop'], 'maxOutsideMkadKm' => $delivery['maxOutsideMkadKm'],
        'freeFromKop' => $delivery['freeFromKop'], 'outsideColor' => $delivery['outsideColor'] ?? null,
        'areas' => array_values(array_map(fn ($a) => ['name' => $a['name'], 'color' => $a['color'], 'priceKop' => $a['priceKop'], 'noDelivery' => !empty($a['noDelivery']), 'points' => $a['points']], $delivery['areas'] ?? [])),
    ],
    'districts' => array_map(fn ($d) => ['name' => $d['name'], 'lat' => $d['lat'], 'lng' => $d['lng'], 'isArea' => !empty($d['isArea'])], $districts),
    'leaflet' => ['css' => View::asset('vendor/leaflet/leaflet.css'), 'js' => View::asset('vendor/leaflet/leaflet.js')],
];
$open = !empty($open);
?>
<div class="zmap" data-zmap>
  <script type="application/json" data-zmap-config><?= json_encode($zmap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <button type="button" class="zmap-toggle" aria-expanded="<?= $open ? 'true' : 'false' ?>" data-zmap-toggle>
    <span class="row" style="gap: 10px"><?= Icons::get('map') ?>Куда возим: карта</span>
    <span class="zmap-chev" aria-hidden="true"><?= Icons::get('chevron', 18) ?></span>
  </button>
  <div class="zmap-body" data-zmap-body<?= $open ? '' : ' hidden' ?>>
    <div class="zmap-map" data-zmap-map></div>
    <div class="zmap-foot">
      <span class="small muted">Нажмите на карту, и покажем, возим ли туда и сколько стоит.<?= $districts ? ' Точки на карте — районы из списка.' : '' ?></span>
      <div class="zmap-legend" data-zmap-legend></div>
    </div>
  </div>
</div>
