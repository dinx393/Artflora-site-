<?php
// Корзина и оформление (повторяет src/components/Checkout.tsx). Разметка здесь, оживляет её assets/js/checkout.js:
// строки корзины и суммы приходят с сервера (/api/cart), заказ уходит на /api/orders.
// На телефоне два шага: «Корзина и доставка» → «Получатель и оплата» (нижняя панель «Далее»).
// Переменные: $shops, $pickupPct, $freeFromKop, $maxOutsideKm, $districts, $leadHours, $delivery
$payments = [
    ['code' => 'ERIP', 'title' => 'ЕРИП', 'note' => 'Покажем, как оплатить, сразу после оформления'],
    ['code' => 'CARD', 'title' => 'WebPay', 'note' => 'Картой онлайн: администратор пришлёт ссылку для оплаты'],
    ['code' => 'INVOICE', 'title' => 'Безнал для организаций', 'note' => 'Выставим счёт на реквизиты компании'],
];
$byn = fn (int $kop) => e(preg_replace('/ BYN$/', '', Money::format($kop))) . '<small class="cur">BYN</small>';
$config = [
    'shops' => $shops, 'pickupPct' => $pickupPct, 'freeFromKop' => $freeFromKop, 'districts' => $districts, 'leadHours' => $leadHours,
    'today' => Slots::minskNow()['date'], 'slots' => Slots::ALL, 'sundayFrom' => Slots::SUNDAY_FROM, 'zones' => $delivery['zones'],
];
?>
<script type="application/json" id="checkout-config"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<div class="section" data-co-empty hidden>
  <div class="empty panel">
    <h1 class="h2">В корзине пока пусто</h1>
    <p class="muted">Выберите букет в каталоге или начните с повода.</p>
    <div class="row" style="justify-content: center; gap: 8px">
      <a href="/catalog/den-materi" class="chip">Маме</a>
      <a href="/catalog/lyubimoj" class="chip">Любимой</a>
      <a href="/catalog/rozy" class="chip">Розы</a>
      <a href="/catalog/buketykompozitsii-do-150-rublej-" class="chip">До 100 BYN</a>
    </div>
    <a href="/catalog" class="btn btn-lg">Перейти в каталог</a>
    <a href="/#hits">Хиты этой недели →</a>
  </div>
</div>

<div class="wrap" data-co>
  <div class="steps desktop-only" style="padding-top: 24px">
    <span class="on">1 Корзина</span>—<span class="on">2 Доставка</span>—<span class="on">3 Получатель и оплата</span>
  </div>
  <div class="mobile-only row between" style="padding-top: 20px">
    <h1 style="font-size: 30px" data-co-mtitle>Корзина и доставка</h1>
    <span class="small muted" data-co-mstep>Шаг 1 из 2</span>
  </div>

  <div class="checkout" data-step="1">
    <div class="checkout-main">
      <?php /* Шаг 1. Корзина */ ?>
      <section class="step-1 stack" style="gap: 12px">
        <a href="/catalog" class="small desktop-only" style="font-weight: 700">← Продолжить покупки</a>
        <h1 class="h2 desktop-only">Ваша <span class="script">корзина</span></h1>
        <p class="muted" data-co-loading>Считаем…</p>
        <p class="error" data-co-removed hidden></p>
        <div data-co-lines></div>
        <div class="zone" style="font-weight: 700">В подарок к заказу: открытка и 2 пакетика подкормки</div>
        <label class="field">
          <span>Текст открытки</span>
          <textarea class="input" rows="2" maxlength="500" data-co-card placeholder="Мама, с днём рождения! Люблю тебя"></textarea>
        </label>
      </section>

      <?php /* Шаг 2. Доставка и время */ ?>
      <section class="step-2 stack" style="gap: 16px">
        <h2 class="h2 desktop-only">Как <span class="script">получить</span></h2>
        <div class="seg" style="align-self: flex-start">
          <button type="button" class="on" data-co-method="DELIVERY">Доставка</button>
          <button type="button" data-co-method="PICKUP">Самовывоз −<?= e($pickupPct) ?>%</button>
        </div>

        <label class="check" data-co-show="delivery">
          <input type="checkbox" data-co-unknown>
          Не знаю адрес, уточните у получателя
        </label>

        <div class="zone" data-co-show="unknown" hidden>
          <b>Адрес и время уточним у получателя</b>
          <span class="small muted">
            Сейчас вы оплачиваете только букет. Мы позвоним получателю, узнаем адрес и удобное время и пришлём вам
            доплату за доставку по обычным тарифам.
          </span>
        </div>

        <div class="stack" style="gap: 18px" data-co-show="address">
          <?php /* Куда везти: цветная кнопка зоны или место на карте (решение владельца 2026-10-23 — без длинного списка районов) */ ?>
          <div class="stack" style="gap: 10px">
            <div class="label">Куда везём?</div>
            <span class="small muted">
              Найдите своё место на карте и нажмите на него, или нажмите кнопку зоны нужного цвета.
              Минск и до <?= e($maxOutsideKm) ?> км за МКАД. Не нашли? Отметьте «Не знаю адрес» выше или напишите нам.
            </span>
            <?= View::render('partials/zones-map', ['delivery' => $delivery, 'districts' => $districts, 'open' => true, 'pick' => true]) ?>
          </div>
          <div class="zone" data-co-zone hidden>
            <div class="row between">
              <b data-co-zone-name></b>
              <span class="price" style="font-size: 24px" data-co-zone-price></span>
            </div>
            <span class="small muted">Привезём <span data-co-zone-shop>из ближайшего магазина</span>, <?= e(Text::fromHours($leadHours)) ?></span>
            <span class="small" style="color: var(--olive); font-weight: 700" data-co-zone-free hidden></span>
          </div>
          <label class="field">
            <span>Улица и дом</span>
            <input class="input" data-co-address placeholder="Например, пр. Независимости, 180" autocomplete="street-address">
          </label>
          <div class="addr-extra">
            <label class="field">
              <span>Квартира / офис</span>
              <input class="input" maxlength="20" inputmode="numeric" data-co-apartment>
            </label>
            <label class="field">
              <span>Подъезд</span>
              <input class="input" maxlength="20" inputmode="numeric" data-co-entrance>
            </label>
            <label class="field">
              <span>Этаж</span>
              <input class="input" maxlength="20" inputmode="numeric" data-co-floor>
            </label>
          </div>
        </div>

        <div class="stack" data-co-show="pickup" hidden>
          <p class="muted">Соберём к выбранному времени и пришлём фото. Скидка <?= e($pickupPct) ?>% на букет.</p>
          <div class="row" style="gap: 8px">
            <?php foreach ($shops as $i => $s): ?>
              <button type="button" class="opt<?= $i === 0 ? ' on' : '' ?>" data-co-shop="<?= e($s['code']) ?>">
                <b><?= e($s['name']) ?></b>
                <span class="note"><?= e($s['address']) ?></span>
              </button>
            <?php endforeach ?>
          </div>
        </div>

        <div class="stack">
          <div class="label" data-co-when>Когда привезти</div>
          <div class="row" style="gap: 8px">
            <button type="button" class="chip on" data-co-day="today">Сегодня</button>
            <button type="button" class="chip" data-co-day="tomorrow">Завтра</button>
            <button type="button" class="chip" data-co-day="other">Другая дата</button>
            <input type="date" class="input" style="width: auto" data-co-date hidden>
          </div>
          <p class="small muted" data-co-show="unknown" hidden>Точное время согласуем с получателем.</p>
          <div class="slots" data-co-show="slots">
            <?php foreach (Slots::ALL as $s): ?>
              <button type="button" class="chip" data-co-slot="<?= e($s) ?>"><?= e($s) ?></button>
            <?php endforeach ?>
          </div>
          <p class="error" data-co-slot-error hidden></p>
        </div>

        <?php /* На телефоне первый шаг сразу показывает, сколько выйдет с доставкой */ ?>
        <div class="mobile-only zone stack" style="gap: 8px">
          <div class="sum-row"><span>Букеты</span><span data-co-m-goods>0 BYN</span></div>
          <div class="sum-row" data-co-show="pickup" hidden><span>Скидка за самовывоз</span><span data-co-m-discount></span></div>
          <div class="sum-row" data-co-show="delivery"><span>Доставка</span><span data-co-m-delivery>выберите, куда</span></div>
          <div class="sum-row" style="align-items: baseline">
            <b>Итого</b>
            <span class="price" style="font-size: 24px" data-co-total><?= $byn(0) ?></span>
          </div>
        </div>
      </section>

      <?php /* Шаг 3. Получатель и оплата */ ?>
      <section class="step-3 stack" style="gap: 16px">
        <h2 class="h2 desktop-only">Получатель <span class="script">и оплата</span></h2>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px">
          <label class="field">
            <span>Ваше имя</span>
            <input class="input" autocomplete="name" data-co-customer-name>
          </label>
          <label class="field">
            <span>Ваш телефон</span>
            <input class="input" type="tel" value="+375 " autocomplete="tel" data-co-customer-phone>
          </label>
          <label class="field" data-co-show="delivery">
            <span>Имя получателя</span>
            <input class="input" placeholder="Если дарите не себе" data-co-recipient-name>
          </label>
          <label class="field" data-co-show="delivery">
            <span data-co-recipient-label>Телефон получателя</span>
            <input class="input" type="tel" data-co-recipient-phone>
          </label>
        </div>
        <div class="stack" style="gap: 8px" data-co-show="delivery">
          <label class="check"><input type="checkbox" checked data-co-call>Позвонить получателю перед доставкой</label>
          <label class="check"><input type="checkbox" data-co-anonymous>Не говорить, от кого букет</label>
        </div>
        <label class="field">
          <span>Комментарий для флориста</span>
          <textarea class="input" rows="2" placeholder="Например: подъезд со двора, домофон 25" data-co-comment></textarea>
        </label>
        <div class="label">Оплата</div>
        <div class="row" style="gap: 8px; align-items: stretch">
          <?php foreach ($payments as $i => $p): ?>
            <button type="button" class="opt<?= $i === 0 ? ' on' : '' ?>" style="flex: 1 1 180px" data-co-payment="<?= e($p['code']) ?>">
              <b style="font-size: 16px"><?= e($p['title']) ?></b>
              <span class="note"><?= e($p['note']) ?></span>
            </button>
          <?php endforeach ?>
        </div>
        <label class="field" data-co-company hidden>
          <span>Реквизиты компании</span>
          <textarea class="input" rows="6" data-co-company-text placeholder="Вставьте реквизиты одним текстом: название организации, УНП, юридический адрес, расчётный счёт, банк и БИК"></textarea>
          <span class="small error" data-co-company-hint hidden></span>
        </label>
        <label class="check consent"><input type="checkbox" data-co-consent>
          <span>Согласен(на) на обработку моих данных и данных получателя для выполнения заказа по <a href="/privacy" target="_blank">Политике обработки персональных данных</a></span>
        </label>
      </section>
    </div>

    <?php /* Итог */ ?>
    <aside class="summary card desktop-only" data-co-summary>
      <h3 style="font-size: 28px">Ваш заказ</h3>
      <div class="sum-row"><span data-co-count>Букеты × 0</span><span data-co-bouquets>0 BYN</span></div>
      <div class="sum-row" data-co-addons-row hidden><span>Дополнения</span><span data-co-addons></span></div>
      <div class="sum-row"><span>Открытка и подкормка</span><span class="gift">в подарок</span></div>
      <div class="sum-row" data-co-show="pickup" hidden><span>Скидка за самовывоз</span><span data-co-discount></span></div>
      <div class="sum-row" data-co-show="delivery"><span>Доставка</span><span data-co-delivery>выберите, куда</span></div>
      <div class="sum-row" style="align-items: baseline; border-top: 1px solid var(--line); padding-top: 12px">
        <b>Итого</b>
        <span class="sum-total" data-co-total><?= $byn(0) ?></span>
      </div>
      <div class="guarantee compact">
        <i><?= Icons::get('shield', 20) ?></i>
        <span class="stack" style="gap: 2px">
          <b>Гарантия свежести 24 часа</b>
          <span>Цветы — живой организм. Если в первые сутки с букетом что-то случится, напишите нам: мы на связи и поможем с заменой.</span>
        </span>
      </div>
      <div class="notice">
        <span style="color: var(--olive)"><?= Icons::get('leaf') ?></span>
        <span>Цветы — живой материал. Если нужного цветка или оттенка не окажется, флорист подберёт близкую замену и согласует её с вами до отправки.</span>
      </div>
      <p class="error" data-co-error hidden></p>
      <button type="button" class="btn btn-lg btn-block desktop-only" disabled data-co-submit>Оформить заказ</button>
      <p class="small muted desktop-only" data-co-hint hidden></p>
      <p class="small muted">Пришлём фото букета до отправки. Оплата после того, как подтвердим заказ.</p>
    </aside>
  </div>

  <?php /* Нижняя панель на телефоне: шаги */ ?>
  <div class="mobile-bar">
    <button type="button" class="btn btn-outline" style="flex: 0 0 auto" data-co-back hidden>←</button>
    <span class="price" style="font-size: 24px" data-co-total><?= $byn(0) ?></span>
    <button type="button" class="btn" disabled data-co-next>Далее</button>
    <button type="button" class="btn" disabled data-co-submit hidden>Оформить заказ</button>
  </div>
</div>
