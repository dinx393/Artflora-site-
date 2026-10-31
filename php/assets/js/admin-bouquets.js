// Список букетов: переключатель «Показывать» срабатывает сразу, без перезагрузки страницы.
document.addEventListener("click", function (e) {
  var btn = e.target.closest("[data-toggle]");
  if (!btn) return;
  var on = !btn.classList.contains("on");
  btn.classList.toggle("on", on);
  btn.setAttribute("aria-pressed", on ? "true" : "false");
  btn.setAttribute("aria-label", on ? "Выключить" : "Включить");
  ArtfloraAdmin.post("/admin/bouquets/toggle", { id: Number(btn.getAttribute("data-toggle")), enabled: on }).then(function (r) {
    if (r && r.ok) return;
    btn.classList.toggle("on", !on);
    btn.setAttribute("aria-pressed", !on ? "true" : "false");
  });
});

// Поиск и фильтры по списку: коллекция, цветок (в любом размере), вид, цена (как на сайте, размер «на фото»).
// Поиск — все введённые слова в любом порядке, «ё» = «е». Всё выбранное (и вкладка) остаётся в адресе
// и в памяти браузера: открыли букет, поправили, вернулись в «Букеты» любым путём — фильтры на месте.
(function () {
  var input = document.querySelector("[data-bouquet-search]");
  var table = document.querySelector("[data-bouquet-list]");
  if (!input || !table) return;
  var KEY = "artflora-admin-bouquet-filters";
  var FIELDS = ["collection", "flower", "format", "from", "to"];
  var count = document.querySelector("[data-search-count]");
  var empty = document.querySelector("[data-search-empty]");
  var hint = document.querySelector("[data-order-hint]");
  var reset = document.querySelector("[data-filters-reset]");
  var controls = {};
  FIELDS.forEach(function (k) { controls[k] = document.querySelector('[data-filter="' + k + '"]'); });
  function norm(s) { return s.toLowerCase().replace(/ё/g, "е"); }

  // Что сейчас выбрано: { q, collection, flower, format, from, to } — только непустое
  function current() {
    var f = {};
    if (input.value.trim()) f.q = input.value.trim();
    FIELDS.forEach(function (k) { if (controls[k].value !== "") f[k] = controls[k].value; });
    return f;
  }
  function query(f, tab) {
    var p = new URLSearchParams();
    if (tab) p.set("tab", tab);
    Object.keys(f).forEach(function (k) { p.set(k, f[k]); });
    return p.toString();
  }

  // Откуда взять фильтры: из адреса, а если в нём пусто — из памяти браузера (там же и вкладка)
  var url = new URL(location.href);
  var tab = url.searchParams.get("tab") || "all";
  var fromUrl = ["q"].concat(FIELDS).some(function (k) { return url.searchParams.has(k); });
  var saved = null;
  try { saved = JSON.parse(localStorage.getItem(KEY) || "null"); } catch (e) {}
  if (!fromUrl && !url.searchParams.has("tab") && saved && saved.tab && saved.tab !== "all") {
    location.replace("/admin/bouquets?" + query(saved.filters || {}, saved.tab));
    return;
  }
  var start = fromUrl ? {} : (saved && saved.filters) || {};
  if (fromUrl) ["q"].concat(FIELDS).forEach(function (k) { if (url.searchParams.get(k)) start[k] = url.searchParams.get(k); });
  if (start.q) input.value = start.q;
  FIELDS.forEach(function (k) {
    var c = controls[k];
    if (start[k] === undefined) return;
    c.value = start[k];
    if (c.value !== start[k]) c.value = ""; // такой коллекции или цветка в списке уже нет
  });

  function apply() {
    var f = current();
    var words = norm(f.q || "").split(/\s+/).filter(Boolean);
    var from = f.from ? Number(f.from) * 100 : null;
    var to = f.to ? Number(f.to) * 100 : null;
    var rows = table.querySelectorAll("tbody tr[data-id]");
    var found = 0;
    Array.prototype.forEach.call(rows, function (tr) {
      var text = tr.getAttribute("data-search") || "";
      var price = Number(tr.getAttribute("data-price"));
      var ok = words.every(function (w) { return text.indexOf(w) !== -1; })
        && (!f.collection || tr.getAttribute("data-collection") === f.collection)
        && (!f.flower || tr.getAttribute("data-flowers").indexOf("|" + f.flower + "|") !== -1)
        && (!f.format || tr.getAttribute("data-format") === f.format)
        && (from === null || price >= from)
        && (to === null || price <= to);
      tr.hidden = !ok;
      if (ok) found++;
    });
    // заголовок группы (Букет дня, ⭐ избранные…) прячем, если в ней ничего не нашлось
    Array.prototype.forEach.call(table.querySelectorAll("[data-rank-head]"), function (h) {
      h.hidden = !table.querySelector('tbody tr[data-rank="' + h.getAttribute("data-rank-head") + '"]:not([hidden])');
    });
    var active = Object.keys(f).length > 0;
    // Пока что-то отфильтровано, порядок не меняем: соседние букеты скрыты, и стрелки двигали бы их вслепую
    table.classList.toggle("searching", active);
    if (hint) hint.hidden = active;
    reset.hidden = !active;
    count.hidden = !active;
    count.textContent = "Найдено: " + found + " из " + rows.length;
    empty.hidden = !active || found > 0;
    history.replaceState(null, "", "/admin/bouquets?" + query(f, tab === "all" ? "" : tab));
    try { localStorage.setItem(KEY, JSON.stringify({ tab: tab, filters: f })); } catch (e) {}
    Array.prototype.forEach.call(document.querySelectorAll("[data-tab]"), function (a) {
      a.href = "/admin/bouquets?" + query(f, a.getAttribute("data-tab"));
    });
  }
  input.addEventListener("input", apply);
  FIELDS.forEach(function (k) {
    controls[k].addEventListener(controls[k].tagName === "SELECT" ? "change" : "input", apply);
  });
  reset.addEventListener("click", function () {
    input.value = "";
    FIELDS.forEach(function (k) { controls[k].value = ""; });
    apply();
  });
  input.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { input.value = ""; apply(); }
    // Enter при одном найденном букете — сразу открыть его
    if (e.key === "Enter") {
      var shown = table.querySelectorAll("tbody tr[data-id]:not([hidden])");
      if (shown.length === 1) location.href = shown[0].querySelector("a").href;
    }
  });
  // «/» в любом месте страницы — к поиску
  document.addEventListener("keydown", function (e) {
    if (e.key === "/" && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); input.focus(); }
  });
  apply();
  if (!input.value && window.matchMedia("(min-width: 900px)").matches) input.focus();
})();

// Порядок букетов на сайте: тянуть за ⠿ (мышью или пальцем) или стрелки ▲▼, сохраняем сразу.
(function () {
  var list = document.querySelector("[data-bouquet-list] tbody");
  if (!list) return;
  var msg = document.querySelector("[data-order-msg]");
  var dragged = null;
  var lastY = 0;
  var scrollTimer = null;
  function rows() { return Array.prototype.slice.call(list.querySelectorAll("tr[data-id]")); }
  // Букет двигается только внутри своей группы (Букет дня, ⭐ избранные, хиты, остальные): порядок групп сайт держит сам
  function same(a, b) { return a && b && a.getAttribute("data-rank") === b.getAttribute("data-rank"); }
  function save() {
    var ids = rows().map(function (tr) { return Number(tr.getAttribute("data-id")); });
    msg.hidden = false;
    msg.className = "small muted";
    msg.textContent = "Сохраняем порядок…";
    ArtfloraAdmin.post("/admin/bouquets/order", { ids: ids }).then(function (r) {
      msg.className = "small " + (r && r.ok ? "muted" : "error");
      msg.textContent = r && r.ok ? "Порядок сохранён, на сайте уже так же" : (r && r.error) || "Не удалось сохранить порядок";
    });
  }
  list.addEventListener("click", function (e) {
    var b = e.target.closest("[data-step]");
    if (!b) return;
    var tr = b.closest("tr");
    var other = Number(b.getAttribute("data-step")) < 0 ? tr.previousElementSibling : tr.nextElementSibling;
    if (!same(tr, other)) return;
    if (Number(b.getAttribute("data-step")) < 0) list.insertBefore(tr, other);
    else list.insertBefore(other, tr);
    save();
  });
  // Строка под пальцем/мышью: тянем вниз — ставим после неё, вверх — перед ней
  function moveTo(y) {
    var tr = rows().filter(function (r) {
      var box = r.getBoundingClientRect();
      return r !== dragged && same(r, dragged) && y >= box.top && y <= box.bottom;
    })[0];
    if (!tr) return;
    var all = rows();
    list.insertBefore(dragged, all.indexOf(dragged) < all.indexOf(tr) ? tr.nextElementSibling : tr);
  }
  // У края экрана список сам прокручивается, чтобы на телефоне можно было унести букет далеко
  function autoScroll() {
    var edge = 70;
    var dy = lastY < edge ? -12 : lastY > window.innerHeight - edge ? 12 : 0;
    if (dy) {
      window.scrollBy(0, dy);
      moveTo(lastY);
    }
  }
  list.addEventListener("pointerdown", function (e) {
    var h = e.target.closest(".drag-handle");
    if (!h) return;
    e.preventDefault();
    dragged = h.closest("tr");
    dragged.classList.add("dragging");
    lastY = e.clientY;
    h.setPointerCapture(e.pointerId);
    scrollTimer = setInterval(autoScroll, 30);
  });
  list.addEventListener("pointermove", function (e) {
    if (!dragged) return;
    lastY = e.clientY;
    moveTo(e.clientY);
  });
  function end() {
    if (!dragged) return;
    clearInterval(scrollTimer);
    dragged.classList.remove("dragging");
    dragged = null;
    save();
  }
  list.addEventListener("pointerup", end);
  list.addEventListener("pointercancel", end);
})();

// «Перемешивать каждый день» — сохраняется сразу
document.addEventListener("change", function (e) {
  var box = e.target.closest("[data-shuffle-daily]");
  if (!box) return;
  ArtfloraAdmin.post("/admin/bouquets/shuffle-daily", { on: box.checked }).then(function (r) {
    if (!(r && r.ok)) box.checked = !box.checked;
  });
});
