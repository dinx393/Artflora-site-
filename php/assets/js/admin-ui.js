// Общие помощники для редакторов админки (цветы, букет, доставка, настройки).
// Редактор = состояние + функция, которая по состоянию возвращает HTML. После каждого изменения
// HTML строится заново, а фокус и курсор в поле ввода восстанавливаются: печатать можно не отрываясь.
//
//   var ui = AdminUI.mount(box, function (on) {
//     return '<button' + on("click", function () { state.n++; ui.render(); }) + '>' + state.n + '</button>';
//   });
(function () {
  var EVENTS = ["click", "input", "change", "keydown", "focusin", "dragstart", "dragover", "dragend", "drop"];

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // 17250 → «172,50 BYN», 13000 → «130 BYN» (как Money::format)
  function formatByn(kop) {
    var sign = kop < 0 ? "−" : "";
    var abs = Math.abs(Math.round(kop));
    var rub = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    var k = abs % 100;
    return sign + rub + (k ? "," + (k < 10 ? "0" : "") + k : "") + " BYN";
  }

  // «10,5» → 1050; мусор → null
  function parseByn(s) {
    var t = String(s).replace(/\s/g, "").replace(",", ".");
    if (!/^\d+(\.\d{1,2})?$/.test(t)) return null;
    return Math.round(parseFloat(t) * 100);
  }

  // 1050 → «10,50»
  function kopToInput(kop) {
    return (kop / 100).toFixed(2).replace(".", ",");
  }

  // 1.5 → «1,5»
  function qtyText(q) {
    return String(Math.round(q * 100) / 100).replace(".", ",");
  }

  function json(id) {
    return JSON.parse(document.getElementById(id).textContent);
  }

  function attr(name, on) {
    return on ? " " + name : "";
  }

  function keyOf(el, root) {
    if (el.getAttribute("data-k")) return "k:" + el.getAttribute("data-k");
    var all = root.querySelectorAll("input, select, textarea, button");
    return "i:" + Array.prototype.indexOf.call(all, el);
  }

  function findKey(key, root) {
    if (key.slice(0, 2) === "k:") return root.querySelector('[data-k="' + key.slice(2).replace(/"/g, '\\"') + '"]');
    return root.querySelectorAll("input, select, textarea, button")[Number(key.slice(2))] || null;
  }

  function mount(root, view) {
    var handlers = [];
    function on(type, fn) {
      handlers.push(fn);
      return " data-on-" + type + '="' + (handlers.length - 1) + '"';
    }
    function render() {
      var a = document.activeElement;
      var focus = null;
      if (a && a !== root && root.contains(a)) {
        focus = { key: keyOf(a, root) };
        try {
          focus.s = a.selectionStart;
          focus.e = a.selectionEnd;
        } catch (err) {}
      }
      var scrolls = Array.prototype.map.call(root.querySelectorAll("[data-keep-scroll]"), function (el) {
        return [el.getAttribute("data-keep-scroll"), el.scrollTop];
      });
      handlers = [];
      root.innerHTML = view(on);
      scrolls.forEach(function (s) {
        var el = root.querySelector('[data-keep-scroll="' + s[0] + '"]');
        if (el) el.scrollTop = s[1];
      });
      if (focus) {
        var el = findKey(focus.key, root);
        if (el) {
          el.focus({ preventScroll: true });
          try {
            if (focus.s != null) el.setSelectionRange(focus.s, focus.e);
          } catch (err) {}
        }
      }
    }
    // Один обработчик на весь блок: ищем ближайший элемент с data-on-<событие>, как всплытие в браузере.
    EVENTS.forEach(function (type) {
      root.addEventListener(type, function (e) {
        var list = handlers;
        var stopped = false;
        var stop = e.stopPropagation;
        e.stopPropagation = function () {
          stopped = true;
          stop.call(e);
        };
        for (var el = e.target; el && el !== root.parentNode; el = el.parentNode) {
          var i = el.getAttribute && el.getAttribute("data-on-" + type);
          if (i !== null && i !== undefined && list[i]) {
            list[i](e, el);
            if (stopped) break;
          }
        }
      });
    });
    render();
    return { render: render };
  }

  // Сообщение «Сохранено» / ошибка
  function msgHtml(msg, style) {
    if (!msg) return "";
    return '<p class="' + (msg.ok ? "pill ok" : "error") + '"' + (style ? ' style="' + style + '"' : "") + ">" + esc(msg.text) + "</p>";
  }

  var OFFLINE = "Не удалось сохранить: нет связи с сервером. Ничего не потеряно, нажмите «Сохранить» ещё раз.";

  // ArtfloraAdmin.post, но при обрыве связи — понятный ответ вместо исключения
  function post(url, data) {
    return window.ArtfloraAdmin.post(url, data).catch(function () {
      return { ok: false, error: OFFLINE };
    });
  }


  window.AdminUI = {
    esc: esc,
    formatByn: formatByn,
    parseByn: parseByn,
    kopToInput: kopToInput,
    qtyText: qtyText,
    json: json,
    attr: attr,
    mount: mount,
    msgHtml: msgHtml,
    post: post,
    OFFLINE: OFFLINE,
  };
})();
