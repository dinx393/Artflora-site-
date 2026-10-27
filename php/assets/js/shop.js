// Витрина: кнопки «В корзину», выбор размера и допов, фото букета, район доставки, фильтры каталога.
// Повторяет поведение компонентов Node.js: AddToCartButton, BuyBox, Gallery, DeliveryEstimate,
// CatalogFilters, ScrollRow, SortSelect. Корзина — window.ArtfloraCart из site.js.
(function () {
  var KEEP = "artflora-keep-scroll";

  // Ссылки фильтров ведут на новую страницу: запоминаем прокрутку и открытую панель «Фильтры»,
  // чтобы после загрузки всё было как до нажатия (в Node.js это scroll={false}).
  function go(href) {
    var to = new URL(href, location.href);
    if (to.pathname + to.search === location.pathname + location.search) return; // уже на этой странице
    try {
      var panel = document.querySelector("[data-filters]");
      sessionStorage.setItem(KEEP, JSON.stringify({ y: window.scrollY, open: !!(panel && panel.classList.contains("open")) }));
    } catch (e) {}
    location.href = href;
  }

  function restore() {
    var s = null;
    try {
      s = JSON.parse(sessionStorage.getItem(KEEP) || "null");
      sessionStorage.removeItem(KEEP);
    } catch (e) {}
    return s;
  }

  // Кнопка «В корзину» на карточке: на 1,6 секунды показывает «✓ В корзине»
  function cardButtons() {
    document.querySelectorAll("[data-add-to-cart]").forEach(function (btn) {
      var icon = btn.querySelector("[data-icon]");
      var word = btn.querySelector(".pcard-add-word");
      var iconHtml = icon.innerHTML;
      var timer;
      btn.addEventListener("click", function () {
        ArtfloraCart.add({ bouquetId: Number(btn.dataset.bouquet), size: btn.dataset.size, qty: 1 });
        var img = btn.closest(".pcard") && btn.closest(".pcard").querySelector("img");
        ArtfloraFly(btn, img && (img.currentSrc || img.src));
        btn.classList.add("done");
        icon.innerHTML = '<span aria-hidden="true">✓</span>';
        word.textContent = "В корзине";
        clearTimeout(timer);
        timer = setTimeout(function () {
          btn.classList.remove("done");
          icon.innerHTML = iconHtml;
          word.textContent = "В корзину";
        }, 1600);
      });
    });
  }

  // Каталог, блок «Добавить к букету»: нажали на доп — он сразу в корзине, нажали ещё раз — убрали
  function catalogAddons() {
    var list = document.querySelectorAll(".addon-pick[data-addon]");
    if (!list.length) return;
    function show(c) {
      list.forEach(function (btn) {
        var id = Number(btn.dataset.addon);
        var on = c.addons.some(function (a) { return a.addonId === id; });
        btn.classList.toggle("on", on);
        btn.setAttribute("aria-pressed", String(on));
        btn.querySelector("[data-addon-state]").textContent = on ? "✓ В корзине" : "+ Добавить";
      });
    }
    list.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var on = ArtfloraCart.toggleAddon(Number(btn.dataset.addon));
        if (on) ArtfloraFly(btn);
        var text = on
          ? btn.dataset.addonName + " — в корзине" + (ArtfloraCart.get().lines.length ? "" : ". Осталось выбрать букет")
          : btn.dataset.addonName + " — убрали из корзины";
        ArtfloraToast(text, on);
      });
    });
    ArtfloraCart.onChange(show);
    show(ArtfloraCart.get());
  }

  // Страница букета: размер, допы (лежат в корзине сразу), текст открытки, «Добавить в корзину»
  function buyBox() {
    var box = document.querySelector("[data-buybox]");
    if (!box) return;
    var size = box.dataset.size;
    var price = box.querySelector("[data-price]");
    var pickup = box.querySelector("[data-pickup]");

    box.querySelectorAll("[data-size-opt]").forEach(function (opt) {
      opt.addEventListener("click", function () {
        size = opt.dataset.sizeOpt;
        box.querySelectorAll("[data-size-opt]").forEach(function (o) { o.classList.toggle("on", o === opt); });
        price.innerHTML = opt.dataset.priceHtml;
        pickup.textContent = opt.dataset.pickupText;
      });
    });

    var addons = box.querySelectorAll("[data-addon]");
    function showAddons(c) {
      addons.forEach(function (btn) {
        var id = Number(btn.dataset.addon);
        var on = c.addons.some(function (a) { return a.addonId === id; });
        var note = btn.querySelector("span:last-child");
        btn.classList.toggle("on", on);
        btn.setAttribute("aria-pressed", String(on));
        note.className = on ? "in-cart" : "note";
        note.textContent = on ? "✓ В корзине · " + btn.dataset.priceText : "+ Добавить · " + btn.dataset.priceText;
      });
    }
    addons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var on = ArtfloraCart.toggleAddon(Number(btn.dataset.addon));
        if (on) ArtfloraFly(btn);
        ArtfloraToast(btn.dataset.addonName + (on ? " — в корзине" : " — убрали из корзины"), on);
      });
    });
    ArtfloraCart.onChange(showAddons);
    showAddons(ArtfloraCart.get());

    var add = box.querySelector("[data-add-big]");
    var added = box.querySelector("[data-added]");
    var text = box.querySelector("[data-card-text]");
    add.addEventListener("click", function () {
      ArtfloraCart.add({ bouquetId: Number(box.dataset.bouquet), size: size, qty: 1 }, text.value);
      var main = document.querySelector("[data-gallery-main]");
      ArtfloraFly(add, main && (main.currentSrc || main.src));
      // Покупатель сразу видит, что вместе с букетом едут и выбранные допы
      var extra = [];
      addons.forEach(function (btn) { if (btn.classList.contains("on")) extra.push(btn.dataset.addonName); });
      added.querySelector("[data-added-text]").textContent = extra.length
        ? "В корзине букет и " + extra.join(", ") + ". Оформим доставку?"
        : "Букет в корзине. Оформим доставку?";
      add.hidden = true;
      added.hidden = false;
    });
  }

  function gallery() {
    var g = document.querySelector("[data-gallery]");
    if (!g) return;
    var main = g.querySelector("[data-gallery-main]");
    var label = g.querySelector("[data-gallery-label]");
    g.querySelectorAll("[data-photo]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        main.src = btn.dataset.photo;
        label.textContent = btn.dataset.label;
        g.querySelectorAll("[data-photo]").forEach(function (b) { b.classList.toggle("on", b === btn); });
      });
    });
  }

  // «Куда доставить?»: выбрали район — показываем цену и магазин
  function deliveryEstimate() {
    var box = document.querySelector("[data-delivery-estimate]");
    if (!box) return;
    var select = box.querySelector("[data-district]");
    var price = box.querySelector("[data-delivery-price]");
    var shop = box.querySelector("[data-delivery-shop]");
    function show() {
      var opt = select.selectedOptions[0];
      var picked = opt && opt.value !== "";
      price.hidden = shop.hidden = !picked;
      if (picked) {
        price.textContent = opt.dataset.label;
        shop.textContent = "Привезём из магазина " + opt.dataset.shop + ".";
      }
    }
    select.addEventListener("change", show);
    show(); // браузер мог запомнить выбор после «Назад»
  }

  // Ряды, которые листаются вбок (разделы над каталогом, поводы на главной):
  // на телефоне ползунок снизу и затухание у края, пока есть что листать
  function scrollRows() {
    document.querySelectorAll("[data-scroll-row]").forEach(function (wrap) {
      var row = wrap.querySelector("[data-scroll-items]") || wrap.querySelector(".chips-row");
      var track = wrap.querySelector(".scroll-track");
      var thumb = track.querySelector("i");
      function update() {
        var max = row.scrollWidth - row.clientWidth;
        if (row.scrollWidth <= row.clientWidth + 2) {
          track.hidden = true;
          wrap.classList.remove("has-more");
          return;
        }
        var width = (row.clientWidth / row.scrollWidth) * 100;
        track.hidden = false;
        thumb.style.width = width + "%";
        thumb.style.left = (row.scrollLeft / max) * (100 - width) + "%";
        wrap.classList.toggle("has-more", row.scrollLeft + row.clientWidth < row.scrollWidth - 4);
      }
      update();
      row.addEventListener("scroll", update, { passive: true });
      window.addEventListener("resize", update);
    });
  }

  // Фильтры: на ПК все группы раскрыты; на телефоне — кнопка «Фильтры» и сворачиваемые группы
  function filters(kept) {
    var panel = document.querySelector("[data-filters]");
    if (!panel) return;
    var toggle = document.querySelector("[data-filters-toggle]");
    var desktop = window.matchMedia("(min-width: 861px)");
    function sync() {
      if (desktop.matches) panel.querySelectorAll("details").forEach(function (d) { d.open = true; });
    }
    sync();
    desktop.addEventListener("change", sync);

    function setOpen(open) {
      panel.classList.toggle("open", open);
      toggle.classList.toggle("btn-outline", !open);
      toggle.setAttribute("aria-expanded", String(open));
    }
    toggle.addEventListener("click", function () { setOpen(!panel.classList.contains("open")); });
    document.querySelector("[data-filters-done]").addEventListener("click", function () {
      setOpen(false);
      // ждём, пока панель свернётся, иначе прокрутка промахнётся
      requestAnimationFrame(function () {
        document.getElementById("catalog-list").scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
    if (kept && kept.open) setOpen(true);
  }

  function sortSelect() {
    var select = document.querySelector("[data-sort]");
    if (!select) return;
    var hrefs = JSON.parse(select.dataset.sort);
    select.addEventListener("change", function () { go(hrefs[select.value]); });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var kept = restore();
    document.addEventListener("click", function (e) {
      var a = e.target.closest("a[data-keep-scroll]");
      if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      go(a.getAttribute("href"));
    });
    cardButtons();
    catalogAddons();
    buyBox();
    gallery();
    deliveryEstimate();
    scrollRows();
    filters(kept);
    sortSelect();
    if (kept) window.scrollTo(0, kept.y);
  });
})();
