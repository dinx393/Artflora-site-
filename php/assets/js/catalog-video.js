// Видео карточки не имеет src до момента, когда карточка близка к экрану: первая загрузка каталога остаётся фото.
(function () {
  var videos = document.querySelectorAll("video[data-catalog-video][data-src]");
  if (!videos.length) return;
  function activate(video) {
    if (!video.src) {
      video.addEventListener("canplay", function () { video.classList.add("is-ready"); video.play().catch(function () {}); }, { once: true });
      video.src = video.getAttribute("data-src");
      video.load();
    } else video.play().catch(function () {});
  }
  if (!("IntersectionObserver" in window)) return; // без поддержки остаётся обычное фото
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) activate(entry.target);
      else entry.target.pause();
    });
  }, { rootMargin: "240px 0px", threshold: 0.01 });
  videos.forEach(function (video) { observer.observe(video); });
})();
