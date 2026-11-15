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
    // Загрузить фото: input type=file → { ok, path } или { ok: false, error }.
    // Перед отправкой: HEIC (фото iPhone) → JPEG, слишком большое фото — уменьшаем (см. prepareImage).
    upload: function (url, file) {
      return prepareImage(file).then(function (f) {
        var fd = new FormData();
        fd.append("file", f, f.name);
        fd.append("_csrf", token);
        return fetch(url, { method: "POST", headers: { "X-CSRF": token }, body: fd }).then(function (r) {
          return r.json().catch(function () {
            return { ok: false, error: r.status === 413 ? "Фото слишком большое для хостинга" : "Сервер ответил ошибкой " + r.status };
          });
        });
      }, function (e) {
        return { ok: false, error: (e && e.message) || "Не удалось открыть фото" };
      });
    },
  };

  // ---------- Подготовка фото в браузере ----------
  // Сервер принимает JPG/PNG/WebP до 15 МБ (Uploads::MAX_BYTES). Фото HEIC с iPhone (на компьютере, в Windows или
  // в Chrome) переводим в JPEG прямо здесь: в Safari — встроенными средствами, иначе библиотекой vendor/heic-to.js
  // (3 МБ, грузится только когда выбрали HEIC). Фото больше 14 МБ уменьшаем до 4000 px по длинной стороне.
  var MAX_BYTES = 14 * 1024 * 1024;

  function looksHeic(file, head) {
    var brand = head.length >= 12 && String.fromCharCode(head[4], head[5], head[6], head[7]) === "ftyp" ? String.fromCharCode(head[8], head[9], head[10], head[11]) : "";
    return ["heic", "heix", "hevc", "hevx", "heim", "heis", "mif1", "msf1"].indexOf(brand) >= 0 || /\.hei[cf]$/i.test(file.name) || /hei[cf]/i.test(file.type);
  }
  function readHead(file) {
    return file.slice(0, 16).arrayBuffer().then(function (b) { return new Uint8Array(b); });
  }
  function jpegName(name) {
    return (name || "photo").replace(/\.[a-z0-9]+$/i, "") + ".jpg";
  }
  function toFile(blob, name) {
    try { return new File([blob], name, { type: "image/jpeg" }); } catch (e) { blob.name = name; return blob; }
  }
  // Картинка, которую умеет открыть сам браузер → JPEG не больше maxSide px
  function redraw(file, maxSide) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        var k = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement("canvas");
        c.width = Math.round(img.naturalWidth * k);
        c.height = Math.round(img.naturalHeight * k);
        c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { b ? resolve(toFile(b, jpegName(file.name))) : reject(new Error("Не удалось обработать фото")); }, "image/jpeg", 0.9);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error("browser-cannot-open")); };
      img.src = url;
    });
  }
  var heicLib = null;
  function loadHeicLib() {
    if (window.HeicTo) return Promise.resolve(window.HeicTo);
    if (heicLib) return heicLib;
    var src = (document.querySelector('script[src*="/assets/js/admin.js"]') || {}).src || "/assets/js/admin.js";
    heicLib = new Promise(function (resolve, reject) {
      var s = document.createElement("script");
      s.src = src.replace(/admin\.js(\?.*)?$/, "vendor/heic-to.js$1");
      s.onload = function () { window.HeicTo ? resolve(window.HeicTo) : reject(new Error("no HeicTo")); };
      s.onerror = function () { heicLib = null; reject(new Error("Не удалось загрузить переводчик HEIC, проверьте интернет")); };
      document.head.appendChild(s);
    });
    return heicLib;
  }
  function fromHeic(file) {
    return redraw(file, 4000).catch(function () { // Safari открывает HEIC сам
      return loadHeicLib().then(function (heicTo) {
        return heicTo({ blob: file, type: "image/jpeg", quality: 0.9 });
      }).then(function (b) {
        var f = toFile(b, jpegName(file.name));
        return f.size > MAX_BYTES ? redraw(f, 4000) : f;
      }, function (e) {
        throw new Error(e && e.message && e.message.indexOf("интернет") >= 0 ? e.message : "Не удалось перевести фото HEIC в JPEG");
      });
    });
  }
  function prepareImage(file) {
    return readHead(file).then(function (head) {
      if (looksHeic(file, head)) return fromHeic(file);
      if (file.size > MAX_BYTES) return redraw(file, 4000).catch(function () { return file; });
      return file;
    });
  }
  window.ArtfloraAdmin.prepareImage = prepareImage;

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
