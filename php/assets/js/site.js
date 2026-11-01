// Общий код для всех страниц магазина: корзина в браузере, меню на телефоне, выбор мессенджера у номера.
// Корзина живёт в браузере (localStorage, тот же ключ, что у версии на Node.js).
// Цены при оформлении всегда пересчитывает сервер: здесь только id букетов, размеры и количество.
(function () {
  var KEY = "artflora-cart-v1";
  var EMPTY = { lines: [], addons: [], cardText: "" };
  var listeners = [];

  function read() {
    try {
      var raw = localStorage.getItem(KEY);
      var c = raw ? JSON.parse(raw) : {};
      return { lines: c.lines || [], addons: c.addons || [], cardText: c.cardText || "" };
    } catch (e) {
      return JSON.parse(JSON.stringify(EMPTY));
    }
  }

  function write(c) {
    try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) {}
    listeners.forEach(function (fn) { fn(c); });
    updateBadges(c);
  }

  // В счётчике у кнопки «Корзина» и букеты, и допы (конфеты, шары): добавили конфеты — цифра выросла
  function count(c) {
    return c.lines.reduce(function (s, l) { return s + l.qty; }, 0) + c.addons.reduce(function (s, a) { return s + a.qty; }, 0);
  }

  function hasAddon(c, id) {
    return c.addons.some(function (a) { return a.addonId === id; });
  }

  // Всплывающая плашка внизу экрана: «Конфеты в корзине · Перейти в корзину»
  var toastTimer;
  function toast(text, link) {
    var el = document.querySelector("[data-toast]");
    if (!el) {
      el = document.createElement("div");
      el.className = "toast";
      el.setAttribute("data-toast", "");
      el.setAttribute("role", "status");
      el.innerHTML = '<span class="toast-ok" aria-hidden="true">✓</span><span data-toast-text></span><a href="/cart" data-toast-link>В корзину →</a>';
      document.body.appendChild(el);
    }
    el.querySelector("[data-toast-text]").textContent = text;
    el.querySelector("[data-toast-link]").hidden = !link;
    el.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove("show"); }, 3500);
  }
  window.ArtfloraToast = toast;

  // «Чик — и улетает в корзину»: кружок с фото букета (или значком) летит от кнопки к «Корзине» в шапке,
  // счётчик подпрыгивает. Кто выключил анимации в системе (prefers-reduced-motion), видит только подпрыгивание.
  function bump(cart) {
    cart.classList.remove("cart-bump");
    void cart.offsetWidth; // перезапуск анимации, если нажали несколько раз подряд
    cart.classList.add("cart-bump");
  }
  function fly(from, photo) {
    var cart = Array.prototype.find.call(document.querySelectorAll("[data-cart-link]"), function (el) {
      var r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    });
    if (!cart) return;
    var calm = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (calm || !from || !from.getBoundingClientRect || !document.body.animate) { bump(cart); return; }
    var a = from.getBoundingClientRect();
    var b = cart.getBoundingClientRect();
    var size = 56;
    var dot = document.createElement("div");
    dot.className = "fly-dot";
    dot.setAttribute("aria-hidden", "true");
    if (photo) dot.style.backgroundImage = "url(" + JSON.stringify(photo) + ")";
    else dot.textContent = "✓";
    dot.style.left = a.left + a.width / 2 - size / 2 + "px";
    dot.style.top = a.top + a.height / 2 - size / 2 + "px";
    document.body.appendChild(dot);
    var dx = b.left + b.width / 2 - (a.left + a.width / 2);
    var dy = b.top + b.height / 2 - (a.top + a.height / 2);
    // Летит по дуге: сначала чуть вверх, потом в корзину, уменьшаясь
    var anim = dot.animate([
      { transform: "translate(0, 0) scale(.6)", opacity: 0 },
      { transform: "translate(" + dx * 0.3 + "px, " + (Math.min(dy, 0) * 0.3 - 60) + "px) scale(1.1)", opacity: 1, offset: 0.3 },
      { transform: "translate(" + dx + "px, " + dy + "px) scale(.3)", opacity: 0.7 },
    ], { duration: 700, easing: "cubic-bezier(.5, 0, .3, 1)" });
    anim.onfinish = function () { dot.remove(); bump(cart); };
  }
  window.ArtfloraFly = fly;

  function updateBadges(c) {
    var n = count(c || read());
    document.querySelectorAll("[data-cart-count]").forEach(function (el) { el.textContent = n; });
    document.querySelectorAll("[data-cart-link]").forEach(function (el) { el.setAttribute("aria-label", "Корзина, товаров: " + n); });
  }

  // Цели для Яндекс Метрики, Facebook и Google (через GTM): «в корзину» (busket — так, с опечаткой, цель заведена в Метрике), «оформление» (checkout), «заказ оформлен» (order) — busket и order уже были заведены в Метрике 55261273.
  // Счётчики работают только после «Принять» на плашке cookie (partials/counters.php): до этого цели ждут в очереди
  // и уходят, как только посетитель согласится. Если отказался — не уходят совсем.
  // ArtfloraGoal({ ym: "order", fb: "Purchase", params: { order_price: 180, currency: "BYN" } })
  var goals = [], waits = 0;
  function sendGoals() {
    if (!window.afCountersOn || !goals.length) return;
    // Метрику загружает GTM, она появляется не сразу: подождём до 15 секунд
    if (!window.ym && waits < 30) { waits++; setTimeout(sendGoals, 500); return; }
    goals.splice(0).forEach(function (g) {
      try {
        if (window.ym && window.afYmId) window.ym(window.afYmId, "reachGoal", g.ym, g.params || {});
        if (window.fbq && g.fb) window.fbq("track", g.fb, g.fbParams || {});
        (window.dataLayer = window.dataLayer || []).push(Object.assign({ event: "af_" + g.ym }, g.params || {}));
      } catch (e) {}
    });
  }
  window.ArtfloraGoal = function (g) { goals.push(g); sendGoals(); };
  window.afGoalsFlush = sendGoals;

  // Откуда пришёл покупатель — уходит вместе с заказом, видно в админке и Telegram (решение 2026-11-01).
  // Запоминаем на 30 дней: метку из рекламной ссылки (utm_source / utm_campaign) или сайт, с которого перешли.
  // Реклама перезаписывает прежний источник, поиск и соцсети — только если ничего не запомнено.
  (function () {
    var KEY_SRC = "af-source", p = new URLSearchParams(location.search), src = "", strong = false;
    if (p.get("utm_source")) { src = p.get("utm_source") + (p.get("utm_campaign") ? " / " + p.get("utm_campaign") : ""); strong = true; }
    else if (p.get("yclid")) { src = "Реклама Яндекса"; strong = true; }
    else if (p.get("gclid")) { src = "Реклама Google"; strong = true; }
    else if (p.get("fbclid")) src = "Instagram / Facebook";
    else if (document.referrer) {
      var h = "";
      try { h = new URL(document.referrer).hostname.replace(/^www\./, ""); } catch (e) {}
      if (h && h !== location.hostname) {
        src = /yandex|ya\.ru/.test(h) ? (/maps/.test(document.referrer) ? "Яндекс Карты" : "Поиск Яндекс")
          : /google/.test(h) ? "Поиск Google" : /instagram/.test(h) ? "Instagram" : /facebook|fb\./.test(h) ? "Facebook"
          : /2gis/.test(h) ? "2ГИС" : h;
      }
    }
    try {
      var old = JSON.parse(localStorage.getItem(KEY_SRC) || "null");
      if (old && Date.now() - old.t > 30 * 86400000) old = null;
      if (src && (strong || !old)) localStorage.setItem(KEY_SRC, JSON.stringify({ s: src.slice(0, 100), t: Date.now() }));
    } catch (e) {}
  })();
  window.ArtfloraSource = function () {
    try {
      var v = JSON.parse(localStorage.getItem("af-source") || "null");
      return v && Date.now() - v.t <= 30 * 86400000 ? v.s : "";
    } catch (e) { return ""; }
  };

  // ArtfloraCart.add({ bouquetId: 5, size: "M", qty: 1 }, "текст открытки")
  window.ArtfloraCart = {
    get: read,
    set: function (c) { write(c); },
    update: function (fn) { write(fn(read())); },
    count: function () { return count(read()); },
    clear: function () { write(JSON.parse(JSON.stringify(EMPTY))); },
    add: function (line, cardText) {
      var c = read();
      var found = c.lines.find(function (l) { return l.bouquetId === line.bouquetId && l.size === line.size; });
      if (found) found.qty = Math.min(20, found.qty + line.qty);
      else c.lines.push({ bouquetId: line.bouquetId, size: line.size, qty: line.qty });
      if (cardText && cardText.trim()) c.cardText = cardText;
      write(c);
      window.ArtfloraGoal({ ym: "busket", fb: "AddToCart", fbParams: { content_ids: [String(line.bouquetId)], content_type: "product" } });
    },
    hasAddon: function (id) { return hasAddon(read(), id); },
    // Положить доп в корзину или убрать его оттуда. Возвращает true, если теперь он в корзине.
    toggleAddon: function (id) {
      var c = read();
      var on = !hasAddon(c, id);
      c.addons = on ? c.addons.concat([{ addonId: id, qty: 1 }]) : c.addons.filter(function (a) { return a.addonId !== id; });
      write(c);
      return on;
    },
    onChange: function (fn) { listeners.push(fn); },
  };

  // Корзину поменяли в другой вкладке
  window.addEventListener("storage", function (e) { if (e.key === KEY) updateBadges(); });

  document.addEventListener("DOMContentLoaded", function () {
    updateBadges();

    // Номер в шапке: выпадающий выбор мессенджера
    document.querySelectorAll("[data-phone-menu]").forEach(function (box) {
      var btn = box.querySelector("button");
      var pop = box.querySelector(".phone-pop");
      function set(open) { pop.hidden = !open; btn.setAttribute("aria-expanded", String(open)); }
      btn.addEventListener("click", function () { set(pop.hidden); });
      document.addEventListener("mousedown", function (e) { if (!box.contains(e.target)) set(false); });
      document.addEventListener("keydown", function (e) { if (e.key === "Escape") set(false); });
      pop.addEventListener("click", function (e) { if (e.target.closest("a")) set(false); });
    });

    // Меню на телефоне. Открыто — фокус внутри меню (Tab не уходит на страницу за ним),
    // остальная страница недоступна (inert), после закрытия фокус возвращается на кнопку меню.
    var menu = document.querySelector("[data-mmenu]");
    var opener = document.querySelector("[data-mmenu-open]");
    if (menu && opener) {
      var hiddenBehind = [];
      var setBackground = function (off) {
        hiddenBehind.forEach(function (el) { el.inert = false; el.removeAttribute("aria-hidden"); });
        hiddenBehind = [];
        if (!off) return;
        for (var node = menu; node && node !== document.body; node = node.parentElement) {
          Array.prototype.forEach.call(node.parentElement.children, function (el) {
            if (el === node || el.tagName === "SCRIPT" || el.inert) return;
            el.inert = true;
            el.setAttribute("aria-hidden", "true");
            hiddenBehind.push(el);
          });
        }
      };
      var focusables = function () {
        return Array.prototype.filter.call(
          menu.querySelectorAll("a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex='-1'])"),
          function (el) { return el.offsetParent !== null || el === document.activeElement; }
        );
      };
      var setMenu = function (open) {
        if (menu.hidden === !open) return;
        menu.hidden = !open;
        opener.setAttribute("aria-expanded", String(open));
        document.body.style.overflow = open ? "hidden" : "";
        setBackground(open);
        if (open) menu.querySelector("[data-mmenu-close]").focus();
        else opener.focus();
      };
      opener.setAttribute("aria-haspopup", "dialog");
      opener.addEventListener("click", function () { setMenu(true); });
      menu.querySelector("[data-mmenu-close]").addEventListener("click", function () { setMenu(false); });
      // Любая ссылка в меню закрывает его
      menu.addEventListener("click", function (e) { if (e.target.closest("a")) setMenu(false); });
      document.addEventListener("keydown", function (e) {
        if (menu.hidden) return;
        if (e.key === "Escape") { setMenu(false); return; }
        if (e.key !== "Tab") return;
        var list = focusables();
        if (!list.length) return;
        var first = list[0], last = list[list.length - 1];
        if (e.shiftKey && (document.activeElement === first || !menu.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && (document.activeElement === last || !menu.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
      });
    }
  });
})();
