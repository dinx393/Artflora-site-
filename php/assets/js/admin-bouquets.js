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

// Порядок букетов на сайте: перетаскивание за ⠿ или стрелки ▲▼, сохраняем сразу.
(function () {
  var list = document.querySelector("[data-bouquet-list] tbody");
  if (!list) return;
  var msg = document.querySelector("[data-order-msg]");
  var dragged = null;
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
  list.addEventListener("dragstart", function (e) {
    if (!e.target.classList || !e.target.classList.contains("drag-handle")) return;
    dragged = e.target.closest("tr");
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("text/plain", dragged.getAttribute("data-id"));
    e.dataTransfer.setDragImage(dragged, 20, 20);
    dragged.classList.add("dragging");
  });
  list.addEventListener("dragover", function (e) {
    if (!dragged) return;
    e.preventDefault();
    var tr = e.target.closest("tr[data-id]");
    if (!tr || tr === dragged) return;
    // тянем вниз — ставим после строки, вверх — перед ней
    var all = rows();
    list.insertBefore(dragged, all.indexOf(dragged) < all.indexOf(tr) ? tr.nextElementSibling : tr);
  });
  list.addEventListener("drop", function (e) { if (dragged) e.preventDefault(); });
  list.addEventListener("dragend", function () {
    if (!dragged) return;
    dragged.classList.remove("dragging");
    dragged = null;
    save();
  });
})();
