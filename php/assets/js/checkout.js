// Корзина и оформление заказа (повторяет src/components/Checkout.tsx). Разметка — app/views/shop/cart.php.
// Цены считает сервер (/api/cart): браузер присылает только id букетов, размеры, количество и район.
(function () {
  var root = document.querySelector("[data-co]");
  if (!root) return;
  var cfg = JSON.parse(document.getElementById("checkout-config").textContent);
  var Cart = window.ArtfloraCart;
  var $ = function (sel) { return root.querySelector(sel); };
  var $$ = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };

  // UUID v4. crypto.randomUUID есть не везде (например, сайт открыт по адресу в домашней сети без https).
  function newKey() {
    var b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    var h = Array.prototype.map.call(b, function (x) { return (x < 16 ? "0" : "") + x.toString(16); }).join("");
    return h.slice(0, 8) + "-" + h.slice(8, 12) + "-" + h.slice(12, 16) + "-" + h.slice(16, 20) + "-" + h.slice(20);
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
  }

  // 17250 → «172,50 BYN», 13000 → «130 BYN» (как Money::format)
  function formatByn(kop) {
    var sign = kop < 0 ? "−" : "";
    var abs = Math.abs(Math.round(kop));
    var rub = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    var k = abs % 100;
    return sign + rub + (k ? "," + (k < 10 ? "0" : "") + k : "") + " BYN";
  }
  // Цифры шрифтом заголовков, «BYN» мельче и светлее
  function byn(kop) {
    return esc(formatByn(kop).replace(/ BYN$/, "")) + '<small class="cur">BYN</small>';
  }

  // Время по Минску (UTC+3) и интервалы, как Slots.php
  function minskNow() {
    var d = new Date(Date.now() + 3 * 3600 * 1000);
    return { date: d.toISOString().slice(0, 10), hour: d.getUTCHours(), minute: d.getUTCMinutes() };
  }
  function addDays(iso, n) {
    return new Date(Date.parse(iso + "T00:00:00Z") + n * 86400000).toISOString().slice(0, 10);
  }
  function startHour(s) {
    var hm = s.split("–")[0].split(":"); // «9:30–10:30» → 9.5
    return parseInt(hm[0], 10) + (parseInt(hm[1] || "0", 10) / 60);
  }
  // В воскресенье — с 10:00 (Slots::forDate)
  function slotsForDate(date) {
    if (new Date(Date.parse(date + "T00:00:00Z")).getUTCDay() !== 0) return cfg.slots.slice();
    return cfg.slots.filter(function (s) { return startHour(s) >= cfg.sundayFrom; });
  }
  function availableSlots(date, lead) {
    var m = minskNow();
    if (date < m.date) return [];
    if (date > m.date) return slotsForDate(date);
    var ready = m.hour + m.minute / 60 + lead;
    return slotsForDate(date).filter(function (s) { return startHour(s) >= ready; });
  }

  var today = minskNow().date;
  var st = {
    step: 1,
    method: "DELIVERY",
    unknown: false,
    place: null, // куда везти: выбирают на карте или кнопкой зоны (delivery-map.js)
    shop: cfg.shops[0] ? cfg.shops[0].code : "",
    dayMode: "today",
    otherDate: addDays(today, 2),
    slot: "",
    payment: "ERIP",
    totals: null,
    error: null, // { text, field }
    sending: false,
    key: newKey(), // ключ этого оформления: повтор запроса вернёт тот же заказ, а не создаст второй
  };

  var inputs = {
    card: $("[data-co-card]"),
    address: $("[data-co-address]"),
    apartment: $("[data-co-apartment]"),
    entrance: $("[data-co-entrance]"),
    floor: $("[data-co-floor]"),
    customerName: $("[data-co-customer-name]"),
    customerPhone: $("[data-co-customer-phone]"),
    recipientName: $("[data-co-recipient-name]"),
    recipientPhone: $("[data-co-recipient-phone]"),
    call: $("[data-co-call]"),
    anonymous: $("[data-co-anonymous]"),
    comment: $("[data-co-comment]"),
    consent: $("[data-co-consent]"),
    date: $("[data-co-date]"),
  };
  // Реквизиты для безнала (способ оплаты INVOICE) — одним текстом. Сервер проверяет то же самое (Checkout::parseCompany).
  var companyText = $("[data-co-company-text]");
  function companyProblem() {
    var t = companyText.value.trim();
    if (t.length < 20) return "Укажите реквизиты компании: название, УНП, адрес, расчётный счёт и банк.";
    if (!/(^|\D)\d{9}(\D|$)/.test(t)) return "В реквизитах не хватает УНП (9 цифр).";
    return null;
  }

  inputs.date.min = addDays(today, 1);
  inputs.date.max = addDays(today, 90);
  inputs.date.value = st.otherDate;

  // Выбранное место, если сервер подтвердил, что туда возим
  function placeQuote() {
    var q = st.totals && st.totals.quote;
    return st.place && q && q.ok ? q : null;
  }
  // Как назвать выбор в блоке с ценой
  function placeTitle(q) {
    if (q.area) return q.area.name;
    if (q.outside) return "За МКАД";
    var z = cfg.zones[q.zone - 1];
    if (!z) return "Доставка";
    return q.zone === cfg.zones.length ? "Минск, дальше от магазина (до МКАД)" : "Минск, до " + z.maxKm + " км от магазина";
  }
  function date() {
    return st.dayMode === "today" ? today : st.dayMode === "tomorrow" ? addDays(today, 1) : st.otherDate;
  }
  function digits(s) { return s.replace(/\D/g, ""); }

  // ---- Пересчёт на сервере при каждом изменении корзины, способа или района ----
  var ctrl = null;
  function refresh() {
    var cart = Cart.get();
    if (cart.lines.length === 0) {
      st.totals = null;
      render();
      return;
    }
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    var unknown = st.method === "DELIVERY" && st.unknown;
    fetch("/api/cart", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ cart: cart, method: st.method, place: st.method === "DELIVERY" && !unknown ? st.place : null }),
      signal: ctrl.signal,
    })
      .then(function (r) { return r.json(); })
      .then(function (t) {
        if (!t || !t.priced) return;
        st.totals = t;
        // Убираем из корзины то, что закончилось
        if (t.priced.removed.length) {
          var keep = {};
          t.priced.lines.forEach(function (l) { keep[l.bouquetId + ":" + l.size] = true; });
          var c = Cart.get();
          c.lines = c.lines.filter(function (l) { return keep[l.bouquetId + ":" + l.size]; });
          Cart.set(c); // onChange ниже запустит пересчёт, а надпись «Закончились…» останется
          st.removedNote = t.priced.removed;
        }
        render();
      })
      .catch(function () {});
  }

  function changeLine(bouquetId, size, fn) {
    Cart.update(function (c) {
      c.lines = c.lines.map(function (x) { return x.bouquetId === bouquetId && x.size === size ? fn(x) : x; }).filter(Boolean);
      return c;
    });
  }

  // ---- Строки корзины ----
  function renderLines() {
    var t = st.totals;
    var html = "";
    (t ? t.priced.lines : []).forEach(function (l) {
      var href = "/bouquet/" + encodeURIComponent(l.slug);
      var sizes = l.sizes.length > 1
        ? '<div class="seg" role="group" aria-label="Размер">' + l.sizes.map(function (s) {
            return '<button type="button" class="' + (s.code === l.size ? "on" : "") + '" data-size="' + esc(s.code) + '">' + esc(s.code) + "</button>";
          }).join("") + "</div>"
        : "";
      html +=
        '<div class="line" data-line="' + l.bouquetId + '" data-line-size="' + esc(l.size) + '">' +
        '<a href="' + href + '"><img src="' + esc(l.photo) + '" alt="' + esc(l.name) + '"></a>' +
        '<div class="stack" style="gap: 8px">' +
        '<a href="' + href + '" class="pcard-title" style="font-size: 22px">' + esc(l.name) + "</a>" +
        '<div class="row" style="gap: 10px">' + sizes +
        '<div class="qty"><button type="button" aria-label="Меньше" data-qty="-1">−</button><span>' + l.qty +
        '</span><button type="button" aria-label="Больше" data-qty="1">+</button></div>' +
        '<button type="button" class="btn-ghost small muted" data-remove>Удалить</button>' +
        "</div></div>" +
        '<span class="price line-price">' + byn(l.priceKop * l.qty) + "</span></div>";
    });
    (t ? t.priced.addons : []).forEach(function (a) {
      html +=
        '<div class="row between" style="padding: 12px 0; border-bottom: 1px solid var(--line)">' +
        '<span style="font-weight: 700">' + esc(a.name) + "</span>" +
        '<span class="row" style="gap: 12px">' + esc(formatByn(a.priceKop * a.qty)) +
        '<button type="button" class="btn-ghost small muted" data-remove-addon="' + a.addonId + '">Удалить</button></span></div>';
    });
    $("[data-co-lines]").innerHTML = html;
  }

  $("[data-co-lines]").addEventListener("click", function (e) {
    var addon = e.target.closest("[data-remove-addon]");
    if (addon) {
      var id = Number(addon.getAttribute("data-remove-addon"));
      Cart.update(function (c) { c.addons = c.addons.filter(function (x) { return x.addonId !== id; }); return c; });
      return;
    }
    var line = e.target.closest("[data-line]");
    if (!line) return;
    var id2 = Number(line.getAttribute("data-line"));
    var size = line.getAttribute("data-line-size");
    var btn;
    if ((btn = e.target.closest("[data-size]"))) {
      var to = btn.getAttribute("data-size");
      changeLine(id2, size, function (x) { return Object.assign({}, x, { size: to }); });
    } else if ((btn = e.target.closest("[data-qty]"))) {
      var d = Number(btn.getAttribute("data-qty"));
      changeLine(id2, size, function (x) { return Object.assign({}, x, { qty: Math.max(1, Math.min(20, x.qty + d)) }); });
    } else if (e.target.closest("[data-remove]")) {
      changeLine(id2, size, function () { return null; });
    }
  });

  // ---- Всё остальное на странице по состоянию ----
  function setOn(sel, attr, value) {
    $$(sel).forEach(function (el) { el.classList.toggle("on", el.getAttribute(attr) === value); });
  }
  function show(name, visible) {
    $$('[data-co-show="' + name + '"]').forEach(function (el) { el.hidden = !visible; });
  }
  function setText(sel, text) { $$(sel).forEach(function (el) { el.textContent = text; }); }
  function setHtml(sel, html) { $$(sel).forEach(function (el) { el.innerHTML = html; }); }

  function render() {
    var cart = Cart.get();
    var empty = cart.lines.length === 0;
    document.querySelector("[data-co-empty]").hidden = !empty;
    root.hidden = empty;
    if (empty) return;

    var t = st.totals;
    var lines = t ? t.priced.lines : [];
    var unknown = st.method === "DELIVERY" && st.unknown;
    var d = placeQuote();
    var slots = availableSlots(date(), cfg.leadHours);
    var todaySlots = availableSlots(today, cfg.leadHours);
    // Если на сегодня интервалов не осталось, сразу предлагаем завтра
    if (st.dayMode === "today" && todaySlots.length === 0) st.dayMode = "tomorrow", slots = availableSlots(date(), cfg.leadHours);
    if (slots.indexOf(st.slot) === -1) st.slot = slots[0] || "";

    // Шаги на телефоне
    $(".checkout").setAttribute("data-step", String(st.step));
    $("[data-co-summary]").classList.toggle("desktop-only", st.step < 2);
    setText("[data-co-mtitle]", st.step === 1 ? "Корзина и доставка" : "Получатель и оплата");
    setText("[data-co-mstep]", "Шаг " + st.step + " из 2");

    // Корзина
    $("[data-co-loading]").hidden = !!t;
    var removed = $("[data-co-removed]");
    removed.hidden = !(st.removedNote && st.removedNote.length);
    if (!removed.hidden) removed.textContent = "Закончились и убраны из корзины: " + st.removedNote.join(", ");
    renderLines();
    if (document.activeElement !== inputs.card) inputs.card.value = cart.cardText;

    // Доставка / самовывоз
    setOn("[data-co-method]", "data-co-method", st.method);
    show("delivery", st.method === "DELIVERY");
    show("pickup", st.method === "PICKUP");
    show("unknown", unknown);
    show("address", st.method === "DELIVERY" && !unknown);
    show("slots", !unknown);
    setOn("[data-co-shop]", "data-co-shop", st.shop);
    var zone = $("[data-co-zone]");
    zone.hidden = !d;
    var deliveryKop = t ? t.deliveryKop : 0;
    // Кнопкам зон под картой — сумма букетов, чтобы и там было «Бесплатно» (delivery-map.js)
    if (t) document.dispatchEvent(new CustomEvent("zmap:subtotal", { detail: t.priced.bouquetsKop }));
    if (d) {
      setText("[data-co-zone-name]", placeTitle(d));
      setHtml("[data-co-zone-price]", t.deliveryKop === 0 ? "Бесплатно" : byn(t.deliveryKop));
      setText("[data-co-zone-shop]", d.shopName ? "из магазина " + d.shopName : "из ближайшего магазина");
      var left = Math.max(0, cfg.freeFromKop - t.priced.bouquetsKop);
      var free = $("[data-co-zone-free]");
      free.hidden = !(left > 0 && !d.area && !d.outside);
      free.textContent = "До бесплатной доставки " + formatByn(left);
    }
    var places = root.querySelector("[data-zmap-places]");
    if (places) places.classList.toggle("invalid", !!(st.error && st.error.field === "address" && !d));
    inputs.address.setAttribute("aria-invalid", String(!!(st.error && st.error.field === "address" && d)));

    // Когда
    setText("[data-co-when]", st.method === "DELIVERY" ? "Когда привезти" : "Когда заберёте");
    setOn("[data-co-day]", "data-co-day", st.dayMode);
    $('[data-co-day="today"]').disabled = todaySlots.length === 0;
    inputs.date.hidden = st.dayMode !== "other";
    $$("[data-co-slot]").forEach(function (b) {
      var s = b.getAttribute("data-co-slot");
      b.classList.toggle("on", s === st.slot);
      b.disabled = slots.indexOf(s) === -1;
      b.hidden = b.disabled; // прошедшие интервалы не показываем, их теперь много
    });
    var slotErr = $("[data-co-slot-error]");
    slotErr.hidden = !(st.error && st.error.field === "slot");
    if (!slotErr.hidden) slotErr.textContent = st.error.text;

    // Получатель и оплата
    var recipientOk = !unknown || digits(inputs.recipientPhone.value).length >= 9;
    setText("[data-co-recipient-label]", unknown ? "Телефон получателя — по нему уточним адрес" : "Телефон получателя");
    inputs.recipientPhone.placeholder = unknown ? "+375 …" : "";
    inputs.recipientPhone.setAttribute("aria-invalid", String(!!(st.error && st.error.field === "recipientPhone") || (unknown && !recipientOk && inputs.recipientPhone.value.length > 0)));
    inputs.customerPhone.setAttribute("aria-invalid", String(!!(st.error && st.error.field === "customerPhone")));
    if (st.payment === "SHOP" && st.method !== "PICKUP") st.payment = "ERIP"; // в магазине — только при самовывозе
    setOn("[data-co-payment]", "data-co-payment", st.payment);
    var invoice = st.payment === "INVOICE";
    $("[data-co-company]").hidden = !invoice;
    var companyErr = invoice ? companyProblem() : null;
    // Подсказка прямо под полем (на телефоне итог с подсказками не виден): ошибка с сервера или что не так в тексте
    var serverCompanyErr = st.error && st.error.field === "company" ? st.error.text : "";
    var companyHint = $("[data-co-company-hint]");
    companyHint.textContent = serverCompanyErr || (companyErr && companyText.value.trim() ? companyErr : "");
    companyHint.hidden = !invoice || !companyHint.textContent;
    companyText.setAttribute("aria-invalid", String(!!companyHint.textContent));

    // Итог
    var goods = t ? t.priced.bouquetsKop + t.priced.addonsKop : 0;
    var count = lines.reduce(function (s, l) { return s + l.qty; }, 0);
    setText("[data-co-count]", "Букеты × " + count);
    setText("[data-co-bouquets]", formatByn(t ? t.priced.bouquetsKop : 0));
    var addonsRow = $("[data-co-addons-row]");
    addonsRow.hidden = !(t && t.priced.addonsKop > 0);
    if (t) setText("[data-co-addons]", formatByn(t.priced.addonsKop));
    setText("[data-co-discount]", formatByn(-(t ? t.discountKop : 0)));
    setText("[data-co-m-discount]", formatByn(-(t ? t.discountKop : 0)));
    setText("[data-co-m-goods]", formatByn(goods));
    setText("[data-co-delivery]", unknown ? "доплата после уточнения" : d ? (deliveryKop ? formatByn(deliveryKop) : "бесплатно") : "выберите, куда");
    setHtml("[data-co-m-delivery]", unknown ? "после уточнения" : d ? (deliveryKop ? esc(formatByn(deliveryKop)) : '<span class="gift">бесплатно</span>') : "выберите, куда");
    setHtml("[data-co-total]", byn(t ? t.totalKop : 0));

    // Можно ли дальше / оформить
    var address = inputs.address.value.trim();
    var deliveryReady = st.method === "PICKUP" || unknown || !!d;
    var canNext1 = lines.length > 0;
    var canNext2 = unknown ? slots.length > 0 : deliveryReady && !!st.slot && (st.method === "PICKUP" || address.length > 3);
    var canSubmit = canNext1 && canNext2 && recipientOk && inputs.customerName.value.trim().length > 0 && digits(inputs.customerPhone.value).length >= 9 && inputs.consent.checked && !companyErr;

    var err = $("[data-co-error]");
    err.hidden = !(st.error && st.error.field !== "slot");
    if (!err.hidden) err.textContent = st.error.text;
    $$("[data-co-submit]").forEach(function (b) {
      b.disabled = !canSubmit || st.sending;
      b.textContent = st.sending ? "Отправляем…" : "Оформить заказ";
    });
    var hint = $("[data-co-hint]");
    hint.hidden = canSubmit;
    hint.textContent =
      (!unknown && (!deliveryReady || (st.method === "DELIVERY" && address.length <= 3)) ? (d ? "Укажите адрес доставки. " : "Выберите на карте или кнопкой, куда везти. ") : "") +
      (!unknown && !st.slot ? "Выберите время. " : "") +
      (!recipientOk ? "Укажите телефон получателя. " : "") +
      (!inputs.customerName.value.trim() ? "Укажите имя и телефон. " : "") +
      (companyErr ? companyErr + " " : "") +
      (!inputs.consent.checked ? "Отметьте согласие на обработку данных." : "");

    // Нижняя панель на телефоне
    $("[data-co-back]").hidden = st.step === 1;
    var next = $("[data-co-next]");
    next.hidden = st.step !== 1;
    next.disabled = !canNext1 || !canNext2;
    $(".mobile-bar [data-co-submit]").hidden = st.step === 1;
  }

  // ---- Отправка ----
  function submit() {
    var cart = Cart.get();
    var unknown = st.method === "DELIVERY" && st.unknown;
    st.error = null;
    st.sending = true;
    render();
    fetch("/api/orders", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        cart: cart,
        method: st.method,
        address: st.method === "DELIVERY" && !unknown ? inputs.address.value : "",
        apartment: inputs.apartment.value,
        entrance: inputs.entrance.value,
        floor: inputs.floor.value,
        addressUnknown: unknown,
        place: st.method === "DELIVERY" && !unknown ? st.place : null,
        shop: st.method === "PICKUP" ? st.shop : "",
        date: date(),
        slot: st.slot,
        customerName: inputs.customerName.value,
        customerPhone: inputs.customerPhone.value,
        recipientName: inputs.recipientName.value,
        recipientPhone: inputs.recipientPhone.value,
        callRecipient: st.method === "DELIVERY" && inputs.call.checked,
        anonymous: st.method === "DELIVERY" && inputs.anonymous.checked,
        comment: inputs.comment.value,
        payment: st.payment,
        company: st.payment === "INVOICE" ? { text: companyText.value } : null,
        consent: inputs.consent.checked,
        idempotencyKey: st.key,
      }),
    })
      .then(function (res) { return res.json(); })
      .then(function (r) {
        if (r.ok) {
          Cart.clear();
          location.href = "/order/" + r.number + "?t=" + encodeURIComponent(r.token);
          return;
        }
        st.error = { text: r.error || "Не получилось оформить заказ", field: r.field };
        if (r.field === "address" || r.field === "slot") st.step = 1;
        if (r.field && (r.field.indexOf("customer") === 0 || r.field === "company" || r.field === "recipientPhone")) st.step = 2;
        if (r.removed) refresh();
      })
      .catch(function () {
        st.error = { text: "Нет связи с сайтом. Проверьте интернет и попробуйте ещё раз" };
      })
      .then(function () {
        st.sending = false;
        render();
      });
  }

  // ---- События ----
  $$("[data-co-method]").forEach(function (b) {
    b.addEventListener("click", function () { st.method = b.getAttribute("data-co-method"); refresh(); render(); });
  });
  $("[data-co-unknown]").addEventListener("change", function (e) { st.unknown = e.target.checked; refresh(); render(); });
  document.addEventListener("zmap:place", function (e) {
    st.place = e.detail;
    if (st.error && st.error.field === "address") st.error = null;
    refresh();
    render();
  });
  $$("[data-co-shop]").forEach(function (b) {
    b.addEventListener("click", function () { st.shop = b.getAttribute("data-co-shop"); render(); });
  });
  $$("[data-co-day]").forEach(function (b) {
    b.addEventListener("click", function () { st.dayMode = b.getAttribute("data-co-day"); render(); });
  });
  inputs.date.addEventListener("change", function () { if (inputs.date.value) st.otherDate = inputs.date.value; render(); });
  $$("[data-co-slot]").forEach(function (b) {
    b.addEventListener("click", function () { st.slot = b.getAttribute("data-co-slot"); if (st.error && st.error.field === "slot") st.error = null; render(); });
  });
  $$("[data-co-payment]").forEach(function (b) {
    b.addEventListener("click", function () { st.payment = b.getAttribute("data-co-payment"); render(); });
  });
  inputs.card.addEventListener("input", function () {
    var text = inputs.card.value;
    Cart.update(function (c) { c.cardText = text; return c; });
  });
  ["address", "customerName", "customerPhone", "recipientPhone"].forEach(function (k) {
    inputs[k].addEventListener("input", render);
  });
  companyText.addEventListener("input", function () {
    if (st.error && st.error.field === "company") st.error = null;
    render();
  });
  inputs.consent.addEventListener("change", function () {
    if (st.error && st.error.field === "consent") st.error = null;
    render();
  });
  $$("[data-co-submit]").forEach(function (b) { b.addEventListener("click", submit); });
  $("[data-co-next]").addEventListener("click", function () { st.step = 2; render(); window.scrollTo({ top: 0 }); });
  $("[data-co-back]").addEventListener("click", function () { st.step = 1; render(); });

  // Корзину поменяли (здесь или в другой вкладке): пересчитать. Текст открытки цен не меняет.
  var lastKey = "";
  function onCart() {
    var c = Cart.get();
    var key = JSON.stringify([c.lines, c.addons]);
    if (key !== lastKey) { lastKey = key; refresh(); }
    render();
  }
  Cart.onChange(onCart);
  window.addEventListener("storage", function (e) { if (e.key === "artflora-cart-v1") onCart(); });
  onCart();
})();
