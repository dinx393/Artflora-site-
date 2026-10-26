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

  function count(c) {
    return c.lines.reduce(function (s, l) { return s + l.qty; }, 0);
  }

  function updateBadges(c) {
    var n = count(c || read());
    document.querySelectorAll("[data-cart-count]").forEach(function (el) { el.textContent = n; });
    document.querySelectorAll("[data-cart-link]").forEach(function (el) { el.setAttribute("aria-label", "Корзина, товаров: " + n); });
  }

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
