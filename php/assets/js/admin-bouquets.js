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
