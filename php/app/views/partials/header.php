<?php
// Шапка магазина. Переменные: $shop, $delivery, $contacts
$menuOccasions = array_filter(Occasions::LIST, fn ($o) => in_array($o['slug'], ['mom', 'love', 'birthday', 'colleague', 'nothing'], true));
$call = array_values(array_filter($contacts, fn ($c) => $c['code'] === 'call'))[0] ?? null;
$msg = array_filter($contacts, fn ($c) => in_array($c['code'], ['viber', 'telegram'], true));
$blank = fn (string $href) => str_starts_with($href, 'https://') ? ' target="_blank" rel="noopener"' : '';
?>
<?php /* Вишнёвая полоска — над логотипом (решение владельца 2026-10-19) */ ?>
<div class="ribbon">
  <div class="wrap">
    <b>Сегодня доставим до 21:00</b>
    <i aria-hidden="true">·</i>
    <span class="desktop-only">Доставка от <?= e(Money::format(Delivery::minKop($delivery))) ?>, бесплатно от <?= e(Money::format($delivery['freeFromKop'])) ?></span>
    <i aria-hidden="true" class="desktop-only">·</i>
    <span class="desktop-only">Фото букета перед отправкой</span>
    <span class="mobile-only">Фото до отправки</span>
    <i aria-hidden="true" class="desktop-only">·</i>
    <span class="desktop-only">Самовывоз −<?= e($delivery['pickupDiscountPct']) ?>%</span>
  </div>
</div>
<header class="header">
  <div class="wrap">
    <div class="header-top">
      <button type="button" class="icon-btn mobile-only" aria-label="Меню" aria-expanded="false" data-mmenu-open><?= Icons::get('menu') ?></button>
      <a href="/" class="logo">Артфлора</a>
      <form action="/catalog" class="search" role="search">
        <?= Icons::get('search') ?>
        <label class="visually-hidden" for="q">Поиск</label>
        <input id="q" name="q" type="search" placeholder="Розы, хризантемы, маме…" value="<?= e($_GET['q'] ?? '') ?>">
      </form>
      <div class="desktop-only">
        <?php /* Номер в шапке: по нажатию выпадает выбор — Telegram, WhatsApp, Viber или звонок */ ?>
        <div class="phone-menu" data-phone-menu>
          <button type="button" class="phone" aria-expanded="false" aria-haspopup="menu">
            <span class="phone-icon"><?= Icons::get('phone') ?></span>
            <span class="phone-number"><?= e($shop['phone']) ?></span>
          </button>
          <div class="phone-pop" role="menu" hidden>
            <span class="small muted">Напишите или позвоните нам</span>
            <?php foreach ($contacts as $l): ?>
              <a role="menuitem" href="<?= e($l['href']) ?>" class="phone-opt <?= e($l['code']) ?>"<?= $blank($l['href']) ?>>
                <span class="dot" aria-hidden="true"><?= $l['code'] === 'call' ? Icons::get('phone', 16) : e(mb_substr($l['label'], 0, 1)) ?></span>
                <?= e($l['label']) ?>
              </a>
            <?php endforeach ?>
          </div>
        </div>
      </div>
      <?= View::render('partials/cart-link') ?>
    </div>
    <nav class="nav" aria-label="Разделы">
      <a href="/catalog">Букеты</a>
      <a href="/#occasions">По поводу</a>
      <a href="/catalog?format=BASKET">Композиции</a>
      <a href="/catalog?occasion=wedding">Свадьба</a>
      <a href="/single">Поштучно</a>
      <a href="/delivery">Доставка и оплата</a>
    </nav>
  </div>
</header>

<?php /* Меню на телефоне: «бургер» в шапке открывает экран со всеми разделами и контактами */ ?>
<div class="mmenu" role="dialog" aria-modal="true" aria-label="Меню" data-mmenu hidden>
  <div class="mmenu-top">
    <button type="button" class="icon-btn" aria-label="Закрыть меню" data-mmenu-close><?= Icons::get('close') ?></button>
    <a href="/" class="logo">Артфлора</a>
    <?= View::render('partials/cart-link') ?>
  </div>
  <div class="mmenu-body">
    <form action="/catalog" class="search search-soft" role="search">
      <?= Icons::get('search') ?>
      <label class="visually-hidden" for="mq">Поиск</label>
      <input id="mq" name="q" type="search" placeholder="Розы, хризантемы, маме…">
    </form>
    <nav class="mmenu-nav" aria-label="Разделы">
      <a href="/catalog">Букеты<span aria-hidden="true">→</span></a>
      <div class="mmenu-group">
        <span>По поводу</span>
        <div class="row" style="gap: 8px">
          <?php foreach ($menuOccasions as $o): ?>
            <a href="/catalog?occasion=<?= e($o['slug']) ?>" class="tag"><?= e($o['label']) ?></a>
          <?php endforeach ?>
        </div>
      </div>
      <a href="/catalog?format=BASKET">Композиции<span aria-hidden="true">→</span></a>
      <a href="/catalog?occasion=wedding">Свадьба<span aria-hidden="true">→</span></a>
      <a href="/single">Поштучно<span aria-hidden="true">→</span></a>
      <a href="/delivery" class="plain">Доставка и оплата<span aria-hidden="true">→</span></a>
    </nav>
    <div class="mmenu-contact">
      <span class="eyebrow">Напишите или позвоните</span>
      <?php if ($call): ?><a href="<?= e($call['href']) ?>" class="mmenu-phone"><?= e($shop['phone']) ?></a><?php endif ?>
      <div class="mmenu-msg">
        <?php foreach ($msg as $c): ?>
          <a href="<?= e($c['href']) ?>"<?= $blank($c['href']) ?>><?= e($c['label']) ?></a>
        <?php endforeach ?>
        <a href="<?= e($shop['instagram']) ?>" target="_blank" rel="noreferrer">Instagram</a>
      </div>
    </div>
    <div class="stack small" style="gap: 12px">
      <?php foreach ($delivery['shops'] as $s): ?>
        <div class="stack" style="gap: 2px">
          <b><?= e($s['name']) ?> · <?= e($s['address']) ?></b>
          <span class="muted">Ежедневно <?= e($s['hours']) ?></span>
        </div>
      <?php endforeach ?>
    </div>
  </div>
</div>
