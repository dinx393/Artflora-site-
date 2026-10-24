<?php
// Счётчики со старого artflora.by (решение владельца 2026-10-24: «всё как было»): Яндекс Метрика, Google Tag Manager,
// пиксель Facebook и чат JivoSite. Только на основном домене, чтобы проверки на test.artflora.by не портили статистику.
// $part: 'head' — в <head>, 'body' — сразу после <body>. Google Analytics UA-157357072-1 не переносим: Google его закрыл в 2023.
if (!Seo::isMainSite()) return;
?>
<?php if ($part === 'head'): ?>
<meta name="facebook-domain-verification" content="7yx8swwmmi4i2ioz5m1igtawb1bl9u">
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-MLZM36Z');</script>
<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');
ym(57278797,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});</script>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','112703594081498');fbq('track','PageView');</script>
<script src="https://code.jivosite.com/widget/0HGe0YpigH" async></script>
<?php else: ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MLZM36Z" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<noscript><div><img src="https://mc.yandex.ru/watch/57278797" style="position:absolute;left:-9999px" alt=""></div></noscript>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=112703594081498&amp;ev=PageView&amp;noscript=1"></noscript>
<?php endif ?>
