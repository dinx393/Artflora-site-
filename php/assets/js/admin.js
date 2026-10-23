// Общий код админки. ArtfloraAdmin.post('/admin/flowers/save', данные) — отправить JSON с защитой от подделки
// (заголовок X-CSRF берётся из <meta name="csrf">), ответ — тоже JSON.
(function () {
  var token = (document.querySelector('meta[name="csrf"]') || {}).content || "";
  window.ArtfloraAdmin = {
    post: function (url, data) {
      return fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF": token },
        body: JSON.stringify(data),
      }).then(function (r) {
        return r.json().catch(function () { return { ok: false, error: "Сервер ответил ошибкой " + r.status }; });
      });
    },
    // Загрузить фото: input type=file → { ok, path } или { ok: false, error }
    upload: function (url, file) {
      var fd = new FormData();
      fd.append("file", file);
      fd.append("_csrf", token);
      return fetch(url, { method: "POST", body: fd }).then(function (r) { return r.json(); });
    },
  };

  // На телефоне таблицы показываем карточками: у каждой ячейки подпись из шапки таблицы (data-label).
  // Таблицы цветов, состава и допов рисует JS, поэтому подписываем заново после каждой перерисовки.
  function labelTables() {
    document.querySelectorAll(".admin-main table.table").forEach(function (t) {
      var heads = Array.prototype.map.call(t.querySelectorAll("thead th"), function (th) { return th.textContent.trim(); });
      t.querySelectorAll("tbody tr").forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) {
          if (td.getAttribute("data-label") !== (heads[i] || "")) td.setAttribute("data-label", heads[i] || "");
        });
      });
    });
  }
  document.addEventListener("DOMContentLoaded", function () {
    labelTables();
    var main = document.querySelector(".admin-main");
    if (main && "MutationObserver" in window) new MutationObserver(labelTables).observe(main, { childList: true, subtree: true });
  });
})();
