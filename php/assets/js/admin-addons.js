// Дополнительные товары: конфеты, ваза… (раздел /admin/addons, раньше был внутри «Настроек»).
// Переключатель «На сайте» сохраняет сразу: закончились конфеты — выключили, и они пропали с сайта.
// Название, цену, «В подарок» и порядок сохраняет кнопка «Сохранить».
(function () {
  var U = window.AdminUI;
  var esc = U.esc;
  var data = U.json("admin-data");
  var stamp = data.stamp; // отпечаток данных: сервер не даст молча затереть правки с другого устройства
  var state = {
    addons: data.addons,
    price: {}, // текст поля цены, пока набирают
    msg: null,
    pending: false,
    dirty: false,
  };

  function save(okText) {
    state.pending = true;
    ui.render();
    U.post("/admin/addons/save", { addons: state.addons, stamp: stamp }).then(function (r) {
      state.pending = false;
      state.msg = r.ok ? { ok: true, text: okText || "Сохранено" } : { ok: false, text: r.error || "Ошибка" };
      if (r.ok) {
        stamp = r.stamp;
        state.addons = r.addons; // новые товары получили номера в базе
        state.price = {};
        state.dirty = false;
      }
      ui.render();
    });
  }

  function move(from, to) {
    var next = state.addons.slice();
    var x = next.splice(from, 1)[0];
    next.splice(to, 0, x);
    state.addons = next;
    state.dirty = true;
    ui.render();
  }

  function view(on) {
    var h = "";
    h += '<div class="row between"><h1>Дополнительные товары</h1><button type="button" class="btn"' + U.attr("disabled", state.pending) + on("click", function () { save(); }) + ">" +
      (state.pending ? "Сохраняем…" : "Сохранить") + "</button></div>";
    h += '<span class="muted">То, что покупатель может добавить к букету одним нажатием: в каталоге, на странице букета и в корзине. ' +
      "Закончились конфеты — выключите «На сайте», и они сразу пропадут с сайта (у тех, кто уже положил их в корзину, они не попадут в заказ).</span>";
    h += U.msgHtml(state.msg, "align-self: flex-start");
    if (state.dirty) h += '<p class="small error" style="align-self: flex-start">Есть несохранённые изменения — нажмите «Сохранить».</p>';

    h += '<div class="card stack" style="gap: 12px">';
    h += '<table class="table"><thead><tr><th>Название</th><th>Цена, BYN</th><th>В подарок</th><th>На сайте</th><th></th></tr></thead><tbody>';
    state.addons.forEach(function (a, i) {
      function set(p) {
        state.addons = state.addons.map(function (x, j) { return j === i ? Object.assign({}, x, p) : x; });
        state.dirty = true;
        ui.render();
      }
      var key = a.id || a.code;
      var priceText = state.price[key] !== undefined ? state.price[key] : U.kopToInput(a.priceKop);
      h += "<tr" + (a.active ? "" : ' style="opacity: .55"') + ">";
      h += '<td><input class="input" style="padding: 8px 10px" maxlength="80" data-k="addon-name-' + esc(key) + '" value="' + esc(a.name) + '"' + on("input", function (e) { set({ name: e.target.value }); }) + "></td>";
      h += '<td><input class="num" inputmode="decimal" data-k="addon-price-' + esc(key) + '" value="' + esc(priceText) + '"' + U.attr("disabled", a.included) + on("input", function (e) {
        state.price[key] = e.target.value;
        var k = U.parseByn(e.target.value);
        if (k !== null) set({ priceKop: k });
      }) + "></td>";
      h += '<td><input type="checkbox" title="Бесплатно, входит в каждый заказ (как открытка)"' + U.attr("checked", a.included) + on("change", function (e) { set({ included: e.target.checked }); }) + "></td>";
      h += '<td><button type="button" class="switch ' + (a.active ? "on" : "") + '" aria-label="Показывать на сайте: ' + esc(a.name) + '"' + U.attr("disabled", state.pending) + on("click", function () {
        state.addons = state.addons.map(function (x, j) { return j === i ? Object.assign({}, x, { active: !a.active }) : x; });
        save(a.active ? "«" + a.name + "» убрали с сайта" : "«" + a.name + "» снова на сайте");
      }) + "></button></td>";
      h += '<td style="white-space: nowrap">';
      h += '<button type="button" class="btn-ghost"' + U.attr("disabled", i === 0) + on("click", function () { move(i, i - 1); }) + ' aria-label="Выше">▲</button>';
      h += '<button type="button" class="btn-ghost"' + U.attr("disabled", i === state.addons.length - 1) + on("click", function () { move(i, i + 1); }) + ' aria-label="Ниже">▼</button>';
      // Удалить можно только ещё не сохранённый товар; сохранённый — выключить (он может быть в старых заказах)
      if (!a.id) h += '<button type="button" class="btn-ghost"' + on("click", function () { state.addons = state.addons.filter(function (_, j) { return j !== i; }); ui.render(); }) + ' aria-label="Убрать">×</button>';
      h += "</td></tr>";
    });
    h += "</tbody></table>";
    h += '<button type="button" class="btn-ghost small" style="align-self: flex-start"' + on("click", function () {
      state.addons = state.addons.concat([{ id: null, code: "addon-" + Date.now().toString(36), name: "", priceKop: 0, included: false, active: true }]);
      state.dirty = true;
      ui.render();
    }) + ">+ Добавить товар</button>";
    h += '<span class="small muted">«В подарок» — бесплатно в каждом заказе (открытка, подкормка). Порядок здесь = порядок на сайте.</span>';
    h += "</div>";
    return h;
  }

  var ui = U.mount(document.getElementById("admin-app"), view);
})();
