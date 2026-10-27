<?php
// Карта зон доставки для покупателя (решение владельца 2026-10-23): цвет — цена, как в админке «Доставка».
// Нажатие на карту показывает, возим ли туда и сколько стоит; точка района выбирает его в списке корзины.
// Переменные: $delivery (настройки доставки), $districts (районы: подписи на карте), $open (сразу открыта),
// $title (подпись кнопки, по умолчанию «Куда возим: карта»),
// $pick (в корзине: карта всегда открыта, под ней крупные кнопки зон — ими и нажатием на карту выбирают, куда везти).
// Оживляет assets/js/delivery-map.js; Leaflet грузится только когда карту открыли.
$zmap = [
    'delivery' => [
        'shops' => array_map(fn ($s) => ['name' => $s['name'], 'location' => $s['location']], $delivery['shops']),
        'zones' => $delivery['zones'], 'mkadCenter' => $delivery['mkadCenter'], 'mkadRadiusKm' => $delivery['mkadRadiusKm'],
        'outsideMkadKop' => $delivery['outsideMkadKop'],
        'freeFromKop' => $delivery['freeFromKop'], 'outsideColor' => $delivery['outsideColor'] ?? null,
        // Карта «доставка из филиала» (Delivery::fromShop): другие филиалы и правила для них
        'otherShops' => $delivery['otherShops'] ?? null, 'shopOrder' => $delivery['shopOrder'] ?? null,
        'areas' => array_values(array_map(fn ($a) => ['name' => $a['name'], 'color' => $a['color'], 'priceKop' => $a['priceKop'], 'noDelivery' => !empty($a['noDelivery']), 'points' => $a['points']], $delivery['areas'] ?? [])),
    ],
    'districts' => array_map(fn ($d) => ['name' => $d['name'], 'lat' => $d['lat'], 'lng' => $d['lng'], 'isArea' => !empty($d['isArea'])], $districts),
    'leaflet' => ['css' => View::asset('vendor/leaflet/leaflet.css'), 'js' => View::asset('vendor/leaflet/leaflet.js')],
];
$pick = !empty($pick);
$open = $pick || !empty($open);
?>
<div class="zmap<?= $pick ? ' zmap-pick-mode' : '' ?>" data-zmap<?= $pick ? ' data-zmap-pick-mode' : '' ?>>
  <script type="application/json" data-zmap-config><?= json_encode($zmap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php if (!$pick): ?>
  <button type="button" class="zmap-toggle" aria-expanded="<?= $open ? 'true' : 'false' ?>" data-zmap-toggle>
    <span class="row" style="gap: 10px"><?= Icons::get('map') ?><?= e($title ?? 'Куда возим: карта') ?></span>
    <span class="zmap-chev" aria-hidden="true"><?= Icons::get('chevron', 18) ?></span>
  </button>
  <?php endif ?>
  <div class="zmap-body" data-zmap-body<?= $open ? '' : ' hidden' ?>>
    <div class="zmap-map" data-zmap-map></div>
    <div class="zmap-foot">
      <?php if ($pick): ?>
        <div class="zmap-places" data-zmap-places></div>
        <p class="small muted" data-zmap-tip hidden></p>
      <?php else: ?>
        <span class="small muted">Нажмите на карту, и покажем, возим ли туда и сколько стоит.</span>
        <div class="zmap-legend" data-zmap-legend></div>
      <?php endif ?>
    </div>
  </div>
</div>
