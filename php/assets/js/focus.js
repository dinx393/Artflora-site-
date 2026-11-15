// Кадр фото букета (админка → «✥ кадр»): у <img data-focus="77 42"> отмечена точка, где букет (в % от левого и
// верхнего края). CSS object-position в процентах ставит эту точку не в центр рамки, а «на 77% ширины рамки», поэтому
// считаем сдвиг сами под размер каждой рамки: точка — в центре, но фото не выходит за края. Без JS остаётся
// object-position из атрибута style — тоже близко к нужному.
(function () {
  // точка fx, fy (%) фото iw×ih в рамке bw×bh → значение object-position
  function calc(fx, fy, iw, ih, bw, bh) {
    var k = Math.max(bw / iw, bh / ih); // object-fit: cover
    var w = iw * k, h = ih * k;
    function axis(p, full, box) {
      if (full - box < 1) return 50;
      return Math.max(0, Math.min(100, ((p / 100) * full - box / 2) / (full - box) * 100));
    }
    return axis(fx, w, bw).toFixed(1) + "% " + axis(fy, h, bh).toFixed(1) + "%";
  }
  function fit(img) {
    var f = (img.getAttribute("data-focus") || "").split(" ").map(Number);
    if (f.length !== 2 || isNaN(f[0]) || isNaN(f[1])) return;
    var iw = img.naturalWidth, ih = img.naturalHeight, bw = img.clientWidth, bh = img.clientHeight;
    if (!iw || !ih || !bw || !bh) return;
    img.style.objectPosition = calc(f[0], f[1], iw, ih, bw, bh);
  }
  function fitAll(root) {
    (root || document).querySelectorAll("img[data-focus]").forEach(function (img) {
      if (img.complete) fit(img);
      if (!img.__focusLoad) {
        img.__focusLoad = true;
        img.addEventListener("load", function () { fit(img); });
      }
    });
  }
  window.ArtfloraFocus = fitAll;
  fitAll.calc = calc;
  function start() {
    fitAll();
    var t;
    window.addEventListener("resize", function () { clearTimeout(t); t = setTimeout(fitAll, 100); });
    // корзину, галерею и админку рисует JS — подхватываем новые фото и смену кадра
    if ("MutationObserver" in window) {
      var pending = false;
      new MutationObserver(function () {
        if (pending) return;
        pending = true;
        requestAnimationFrame(function () { pending = false; fitAll(); });
      }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ["data-focus", "src"] });
    }
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
