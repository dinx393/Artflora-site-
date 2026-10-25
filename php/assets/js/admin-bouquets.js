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

// Порядок букетов на сайте: тянуть за ⠿ (мышью или пальцем) или стрелки ▲▼, сохраняем сразу.
(function () {
  var list = document.querySelector("[data-bouquet-list] tbody");
  if (!list) return;
  var msg = document.querySelector("[data-order-msg]");
  var dragged = null;
  var lastY = 0;
  var scrollTimer = null;
  function rows() { return Array.prototype.slice.call(list.querySelectorAll("tr[data-id]")); }
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
    if (!other) return;
    if (Number(b.getAttribute("data-step")) < 0) list.insertBefore(tr, other);
    else list.insertBefore(other, tr);
    save();
  });
  // Строка под пальцем/мышью: тянем вниз — ставим после неё, вверх — перед ней
  function moveTo(y) {
    var tr = rows().filter(function (r) {
      var box = r.getBoundingClientRect();
      return r !== dragged && y >= box.top && y <= box.bottom;
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
