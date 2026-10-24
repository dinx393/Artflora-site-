<?php
// Счётчики со старого artflora.by (решение владельца 2026-10-24): Яндекс Метрика, Google Tag Manager, пиксель Facebook.
// Чат JivoSite не переносим, Google Analytics UA-157357072-1 тоже (Google его закрыл в 2023).
// Только на основном домене, чтобы проверки на test.artflora.by не портили статистику.
// Счётчики ставят cookie, поэтому включаются только после «Принять» на плашке (закон № 99-З, позиция НЦЗПД);
// выбор хранится в браузере (localStorage «af-cookies»: yes/no), изменить его можно на странице /privacy.
// $part: 'head' — в <head>, 'body' — в конце <body>.
if (!Seo::isMainSite()) return;
?>
<?php if ($part === 'head'): ?>
<meta name="facebook-domain-verification" content="7yx8swwmmi4i2ioz5m1igtawb1bl9u">
<script>
window.afCounters = function () {
  if (window.afCountersOn) return; window.afCountersOn = true;
  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-MLZM36Z');
  (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');
  ym(57278797,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
  fbq('init','112703594081498');fbq('track','PageView');
};
try { if (localStorage.getItem('af-cookies') === 'yes') window.afCounters(); } catch (e) {}
</script>
<?php else: ?>
<div class="cookie-bar" data-cookie-bar hidden>
  <span>Мы используем cookie для счётчиков посещаемости (Яндекс Метрика, Google, Facebook). <a href="/privacy">Подробнее</a></span>
  <span class="row" style="gap: 8px">
    <button type="button" class="btn" data-cookie="yes">Принять</button>
    <button type="button" class="btn btn-outline" data-cookie="no">Отклонить</button>
  </span>
</div>
<script>
(function () {
  var bar = document.querySelector('[data-cookie-bar]');
  function get() { try { return localStorage.getItem('af-cookies'); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem('af-cookies', v); } catch (e) {} }
  if (!get()) bar.hidden = false;
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cookie]');
    if (!b) return;
    var v = b.getAttribute('data-cookie');
    set(v);
    bar.hidden = true;
    if (v === 'yes') window.afCounters();
  });
  var reset = document.querySelector('[data-cookie-reset]');
  if (reset) reset.addEventListener('click', function () {
    try { localStorage.removeItem('af-cookies'); } catch (e) {}
    location.reload(); // если счётчики уже работали, перезагрузка их выключит
  });
})();
</script>
<?php endif ?>
