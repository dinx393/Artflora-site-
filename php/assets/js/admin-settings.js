// Настройки = src/app/admin/(panel)/settings/SettingsEditor.tsx
// Контакты, почта для заказов (с пробным письмом), «Букет дня», реквизиты, цвета букетов, дополнения.
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var data = U.json("admin-data");
  var state = {
    shop: data.shop,
    colors: data.colors,
    addons: data.addons,
    addonPrice: {}, // текст поля цены, пока набирают
    msg: null,
    pending: false,
    testing: false,
    mailMsg: null,
  };

  function moveItem(list, from, to) {
    var next = list.slice();
    var x = next.splice(from, 1)[0];
    next.splice(to, 0, x);
    return next;
  }

  function testMail() {
    state.testing = true;
    ui.render();
    var to = state.shop.orderEmail;
    U.post("/admin/settings/test-email", { to: to }).then(function (r) {
      state.testing = false;
      state.mailMsg = r.ok ? { ok: true, text: "Письмо отправлено на " + to + ". Проверьте почту (и папку «Спам»)." } : { ok: false, text: r.error || "Ошибка" };
      ui.render();
    });
  }

  function save() {
    state.pending = true;
    ui.render();
    U.post("/admin/settings/save", { shop: state.shop, colors: state.colors, addons: state.addons }).then(function (r) {
      state.pending = false;
      state.msg = r.ok ? { ok: true, text: "Настройки сохранены" } : { ok: false, text: r.error || "Ошибка" };
      if (r.ok && r.addons) {
        state.addons = r.addons; // новые дополнения получили номера в базе
        state.addonPrice = {};
      }
      ui.render();
    });
  }

  function view(on) {
    var shop = state.shop;
    function field(label, key, placeholder) {
      return '<label class="field"><span>' + esc(label) + '</span><input class="input" data-k="shop-' + key + '" value="' + esc(shop[key]) + '"' +
        (placeholder ? ' placeholder="' + esc(placeholder) + '"' : "") +
        on("input", function (e) { shop[key] = e.target.value; ui.render(); }) + "></label>";
    }
    var h = "";
    h += '<div class="row between"><h1>Настройки</h1><button type="button" class="btn"' + U.attr("disabled", state.pending) + on("click", save) + ">" +
      (state.pending ? "Сохраняем…" : "Сохранить") + "</button></div>";
    h += U.msgHtml(state.msg, "align-self: flex-start");

    h += '<div class="card stack" style="gap: 12px"><span class="label" style="font-size: 18px">Контакты</span>';
    h += '<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px">';
    h += field("Телефон в шапке", "phone");
    h += field("Ссылка Viber", "viber", "viber://chat?number=%2B375...");
    h += field("Ссылка Telegram", "telegram", "https://t.me/...");
    h += field("Ссылка WhatsApp (пусто — по номеру телефона)", "whatsapp", "https://wa.me/375...");
    h += field("Ссылка Instagram", "instagram");
    h += field("Почта для писем о заказах (можно несколько через запятую)", "orderEmail", "zakaz@artflora.by");
    h += "</div>";
    h += '<div class="row" style="gap: 12px; align-items: center"><button type="button" class="btn btn-outline"' + U.attr("disabled", state.testing) + on("click", testMail) + ">" +
      (state.testing ? "Отправляем…" : "Отправить пробное письмо") + '</button><span class="small muted">' +
      (data.smtpReady ? "Почта-отправитель подключена." : "Почта-отправитель ещё не подключена (smtp_… в файле app/config.php, см. README).") + "</span></div>";
    h += U.msgHtml(state.mailMsg, "align-self: flex-start");
    h += field("Строка над каталогом (акция)", "promoLine", "До Дня матери 14 октября · предзаказ −10%");
    h += '<label class="field"><span>«Букет дня» на главной (фото лучше вертикальное, букет по центру)</span><select class="input"' +
      on("change", function (e) { shop.dayBouquet = e.target.value; ui.render(); }) + '><option value="">Сам выберет: первый хит</option>';
    data.bouquets.forEach(function (b) { h += '<option value="' + esc(b.slug) + '"' + U.attr("selected", shop.dayBouquet === b.slug) + ">" + esc(b.name) + "</option>"; });
    h += "</select></label>";
    h += '<label class="field"><span>Реквизиты продавца в подвале (обязательно по закону РБ)</span><textarea class="input" rows="2" data-k="seller"' +
      on("input", function (e) { shop.sellerDetails = e.target.value; ui.render(); }) + ">" + esc(shop.sellerDetails) + "</textarea></label>";
    h += field("Почта для покупателей (в подвале)", "email", "artflora-minsk@yandex.by");
    h += field("Кто рассматривает обращения покупателей (ФИО, должность, телефон)", "complaints");
    h += field("Телефоны райисполкома для обращений (обязательно по закону РБ)", "authority", "Администрация … района г. Минска, отдел торговли: +375 17 …");
    h += '<label class="sum-row" style="justify-content: flex-start; gap: 12px"><span>Принимаем заказ на сегодня минимум за, часов:</span>' +
      '<input class="num" style="width: 90px" type="number" step="0.5" data-k="lead" value="' + (isFinite(shop.leadHours) ? shop.leadHours : "") + '"' +
      on("input", function (e) { shop.leadHours = parseFloat(e.target.value); }) + "></label>";
    h += "</div>";

    h += '<p class="small muted">Зоны доставки, районы и магазины — в разделе <a href="/admin/delivery" style="font-weight: 700">«Доставка»</a>, там они на карте.</p>';

    h += '<div class="card stack" style="gap: 12px"><span class="label" style="font-size: 18px">Цвета букетов</span>';
    h += '<span class="small muted">Из них выбирают цвет в карточке букета, и по ним фильтрует каталог. Нажмите на кружок, чтобы выбрать оттенок. В каталоге видны только цвета, которые есть хотя бы у одного букета.</span>';
    state.colors.forEach(function (c, i) {
      function set(p) {
        state.colors = state.colors.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; });
        ui.render();
      }
      h += '<div class="row color-edit" style="gap: 8px; align-items: center">';
      h += /^#[0-9a-f]{6}$/i.test(c.swatch)
        ? '<input type="color" class="color-pick" value="' + esc(c.swatch) + '" aria-label="Оттенок: ' + esc(c.label) + '"' + on("change", function (e) { set({ swatch: e.target.value }); }) + ">"
        : '<i class="swatch" style="background: ' + esc(c.swatch) + '; width: 36px; height: 36px" title="Микс — разноцветный кружок"></i>';
      h += '<input class="input" style="width: 220px; min-width: 0" maxlength="30" data-k="color-' + esc(c.slug) + '" value="' + esc(c.label) + '"' + on("input", function (e) { set({ label: e.target.value }); }) + ">";
      h += '<button type="button" class="btn-ghost"' + U.attr("disabled", i === 0) + on("click", function () { state.colors = moveItem(state.colors, i, i - 1); ui.render(); }) + ' aria-label="Выше">▲</button>';
      h += '<button type="button" class="btn-ghost"' + U.attr("disabled", i === state.colors.length - 1) + on("click", function () { state.colors = moveItem(state.colors, i, i + 1); ui.render(); }) + ' aria-label="Ниже">▼</button>';
      h += '<button type="button" class="btn-ghost"' + on("click", function () { state.colors = state.colors.filter(function (_, j) { return j !== i; }); ui.render(); }) + ' aria-label="Убрать">×</button>';
      h += "</div>";
    });
    h += '<button type="button" class="btn-ghost small" style="align-self: flex-start"' + on("click", function () {
      state.colors = state.colors.concat([{ slug: "c" + Date.now().toString(36), label: "Новый цвет", swatch: "#88aa66" }]);
      ui.render();
    }) + ">+ Добавить цвет</button></div>";

    h += '<div class="card stack" style="gap: 12px"><span class="label" style="font-size: 18px">Дополнения к букету</span>';
    h += '<table class="table"><thead><tr><th>Название</th><th>Цена, BYN</th><th>Входит в комплект</th><th>Показывать</th></tr></thead><tbody>';
    state.addons.forEach(function (a, i) {
      function set(p) {
        state.addons = state.addons.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; });
        ui.render();
      }
      var key = a.id || a.code;
      var priceText = state.addonPrice[key] !== undefined ? state.addonPrice[key] : U.kopToInput(a.priceKop);
      h += "<tr><td><input class=\"input\" style=\"padding: 8px 10px\" data-k=\"addon-name-" + esc(key) + '" value="' + esc(a.name) + '"' + on("input", function (e) { set({ name: e.target.value }); }) + "></td>";
      h += '<td><input class="num" inputmode="decimal" data-k="addon-price-' + esc(key) + '" value="' + esc(priceText) + '"' + on("input", function (e) {
        state.addonPrice[key] = e.target.value;
        var k = U.parseByn(e.target.value);
        if (k !== null) set({ priceKop: k });
      }) + "></td>";
      h += '<td><input type="checkbox"' + U.attr("checked", a.included) + on("change", function (e) { set({ included: e.target.checked }); }) + "></td>";
      h += '<td><button type="button" class="switch ' + (a.active ? "on" : "") + '" aria-label="Показывать"' + on("click", function () { set({ active: !a.active }); }) + "></button></td></tr>";
    });
    h += "</tbody></table>";
    h += '<button type="button" class="btn-ghost small" style="align-self: flex-start"' + on("click", function () {
      state.addons = state.addons.concat([{ code: "addon-" + Date.now().toString(36), name: "Новое дополнение", priceKop: 0, included: false, active: false }]);
      ui.render();
    }) + ">+ Добавить</button></div>";
    return h;
  }

  var ui = U.mount(document.getElementById("admin-app"), view);
})();
