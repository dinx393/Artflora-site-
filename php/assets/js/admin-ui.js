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

  // Обновить страницу до нового HTML, не пересоздавая элементы: фокус, курсор, открытая палитра цвета
  // и перетаскивание остаются как были. Поле, в котором сейчас печатают, не трогаем, если значение то же.
  function syncAttrs(from, to) {
    var i;
    for (i = from.attributes.length - 1; i >= 0; i--) {
      var n = from.attributes[i].name;
      if (!to.hasAttribute(n)) from.removeAttribute(n);
    }
    for (i = 0; i < to.attributes.length; i++) {
      var a = to.attributes[i];
      if (from.getAttribute(a.name) !== a.value) from.setAttribute(a.name, a.value);
    }
  }

  function morphNode(from, to) {
    if (from.nodeType !== to.nodeType || from.nodeName !== to.nodeName || (from.type || "") !== (to.type || "")) {
      from.parentNode.replaceChild(to, from);
      return;
    }
    if (from.nodeType !== 1) {
      if (from.nodeValue !== to.nodeValue) from.nodeValue = to.nodeValue;
      return;
    }
    syncAttrs(from, to);
    var tag = from.nodeName;
    var focused = from === document.activeElement;
    if (tag === "INPUT") {
      if (from.type === "checkbox" || from.type === "radio") from.checked = to.hasAttribute("checked");
      else if (from.type !== "file") {
        var v = to.getAttribute("value") || "";
        if (from.value !== v) from.value = v;
      }
      return;
    }
    if (tag === "TEXTAREA") {
      if (from.value !== to.textContent && !focused) from.value = to.textContent;
      return;
    }
    morphChildren(from, to);
    if (tag === "SELECT") {
      var sel = to.querySelector("option[selected]");
      from.value = sel ? sel.value : from.options.length ? from.options[0].value : "";
    }
  }

  function morphChildren(from, to) {
    var a = from.firstChild;
    var b = to.firstChild;
    while (b) {
      var nextB = b.nextSibling;
      if (!a) {
        from.appendChild(b);
      } else {
        var nextA = a.nextSibling;
        morphNode(a, b);
        a = nextA;
      }
      b = nextB;
    }
    while (a) {
      var next = a.nextSibling;
      from.removeChild(a);
      a = next;
    }
  }

  function mount(root, view) {
    var handlers = [];
    function on(type, fn) {
      handlers.push(fn);
      return " data-on-" + type + '="' + (handlers.length - 1) + '"';
    }
    function render() {
      handlers = [];
      var tpl = document.createElement("template");
      tpl.innerHTML = view(on);
      morphChildren(root, tpl.content);
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


  // Цена на сайте округляется вверх до 5 BYN — как Pricing::roundUpKop
  function roundPriceKop(kop) {
    return Math.ceil(kop / 500) * 500;
  }

  window.AdminUI = {
    roundPriceKop: roundPriceKop,
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
