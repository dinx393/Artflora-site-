<?php // Раздел «Доставка»: редактор работает в браузере (assets/js/admin-delivery.js), карта — Leaflet из assets/vendor ?>
<script type="application/json" id="admin-data"><?= admin_json($editor) ?></script>
<div id="dl-head" class="row between"></div>
<div class="delivery-grid">
  <div id="dl-left" class="stack" style="gap: 16px; min-width: 0"></div>
  <div class="delivery-map-wrap">
    <div id="dl-map" class="delivery-map"></div>
    <div id="dl-legend" class="map-legend"></div>
  </div>
</div>
