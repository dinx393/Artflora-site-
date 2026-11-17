<?php
// Новый год: оформление «Морозная вишня» и сезонный раздел (Season). Переменные: $cfg, $marked, $saved
$phase = Season::phase();
$cur = Season::current();
$year = (int) Season::today()->format('Y');
$switchValue = $year . '-' . $cfg['switchDate'];
$check = fn (string $k) => !empty($cfg[$k]) ? 'checked' : '';
?>
<div class="stack" style="gap: 4px">
  <h1>Новый год</h1>
  <span class="muted">Праздничное оформление включается и выключается здесь. Основной дизайн сайта не меняется: сняли галочку — сайт сразу снова обычный.</span>
</div>
<style>
.ny-check { display: flex; align-items: flex-start; gap: 10px; } .ny-check input { margin-top: 4px; flex: none; }
.ny-bouquet-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap }
.ny-bouquet-list { display:grid; gap:8px }
.ny-bouquet { display:grid; grid-template-columns:32px 58px minmax(0,1fr) auto; align-items:center; gap:12px; padding:9px 12px; border:1px solid var(--line); border-radius:14px; background:#fff }
.ny-bouquet.dragging { opacity:.45 }.ny-drag { border:0; background:none; color:var(--muted); font-size:22px; cursor:grab; padding:8px 4px }.ny-bouquet-photo { width:58px; height:58px; border-radius:10px; object-fit:cover }
.ny-bouquet-name { font-weight:700; color:var(--cherry); text-decoration:none }.ny-bouquet-actions { display:flex; gap:4px }.ny-step { width:34px; height:34px; padding:0; border-radius:50%; border:1px solid var(--line); background:#fff; color:var(--cherry); font-size:18px }
.ny-frozen { background:#f7f3f8; border-color:#eee6f1 }
@media (max-width:600px) { .ny-bouquet { grid-template-columns:24px 48px minmax(0,1fr) auto; gap:8px; padding:8px }.ny-bouquet-photo{width:48px;height:48px}.ny-drag{display:none}.ny-step{width:30px;height:30px} }
</style>
<?php if ($saved): ?><div class="card" style="background: #eef3e6">Сохранено. <a href="/" target="_blank">Открыть сайт →</a></div><?php endif ?>
<form method="post" action="/admin/season" class="stack" style="gap: 20px">
  <?= Auth::csrfField() ?>
  <div class="card stack" style="gap: 12px">
    <b>Оформление «Морозная вишня»</b>
    <label class="ny-check"><input type="checkbox" name="theme" value="1" <?= $check('theme') ?>><span>Новогоднее оформление сайта<br><span class="small muted">Иней вместо розовых подложек, ёлки, сугроб в подвале, счётчик «дней до Нового года», в декабре — блок «Успеть к празднику» и «Для организаций»</span></span></label>
    <label class="ny-check" style="padding-left: 28px"><input type="checkbox" name="snow" value="1" <?= $check('snow') ?>> Падающий снег</label>
    <label class="ny-check" style="padding-left: 28px"><input type="checkbox" name="garland" value="1" <?= $check('garland') ?>> Гирлянда под шапкой</label>
  </div>
  <div class="card stack" style="gap: 12px">
    <b>Раздел «Зимнее настроение» / «Новый год»</b>
    <label class="ny-check"><input type="checkbox" name="section" value="1" <?= $check('section') ?>> Показывать раздел на виду: первым в меню, первым блоком на главной и первой кнопкой над каталогом</label>
    <label class="stack" style="gap: 6px; max-width: 360px">
      <span>С какого дня раздел называется «Новый год»</span>
      <input class="input" type="date" name="switchDate" value="<?= e($switchValue) ?>">
      <span class="small muted">До этого дня — «Зимнее настроение» (/catalog/<?= Season::WINTER_SLUG ?>), с него — «Новый год» (/catalog/<?= Season::NEWYEAR_SLUG ?>). Сейчас: <b><?= e($cur['label']) ?></b>. Меняется само, переключать не нужно.</span>
    </label>
    <?php if (Season::editingOn()): ?>
      <div class="ny-bouquet-head">
        <span class="small muted">Добавляйте букеты в обычной карточке: «Разделы каталога» → «Зимнее настроение / Новый год». Здесь задаётся только их отдельный порядок. Общий каталог от этого не меняется.</span>
        <b><?= count($marked) ?> <?= e(Season::plural(count($marked), 'букет', 'букета', 'букетов')) ?></b>
      </div>
      <?php if ($marked): ?>
        <div class="ny-bouquet-list" data-season-list>
          <?php foreach ($marked as $b): ?>
            <div class="ny-bouquet" data-season-id="<?= (int) $b['id'] ?>" draggable="true">
              <button class="ny-drag" type="button" title="Перетащить" aria-label="Перетащить букет">⠿</button>
              <img class="ny-bouquet-photo" src="<?= e($b['photo']) ?>" alt="" style="<?= $b['photoPos'] ? 'object-position:' . e($b['photoPos']) : '' ?>">
              <div class="stack" style="gap:3px;min-width:0">
                <a class="ny-bouquet-name" href="/admin/bouquets/<?= (int) $b['id'] ?>"><?= e($b['name']) ?></a>
                <span class="small <?= $b['enabled'] ? 'muted' : '' ?>"><?= $b['enabled'] ? 'На сайте' : 'Выключен' ?></span>
              </div>
              <div class="ny-bouquet-actions">
                <button class="ny-step" type="button" data-season-step="-1" title="Выше" aria-label="Поднять выше">↑</button>
                <button class="ny-step" type="button" data-season-step="1" title="Ниже" aria-label="Опустить ниже">↓</button>
              </div>
            </div>
          <?php endforeach ?>
        </div>
        <span class="small muted" data-season-message>Перетащите строку; на телефоне используйте стрелки. Порядок сохраняется сразу.</span>
      <?php else: ?>
        <div class="ny-frozen card small">Пока ни одного букета. Откройте нужный букет и отметьте зимний раздел — он сразу появится здесь.</div>
      <?php endif ?>
    <?php else: ?>
      <div class="ny-frozen card stack" style="gap:5px">
        <b>Зимний список заморожен</b>
        <span class="small muted">Сохранено <?= count($marked) ?> <?= e(Season::plural(count($marked), 'букет', 'букета', 'букетов')) ?> и их порядок. В карточках букетов зимняя плашка скрыта, поэтому её нельзя поставить случайно. Включите «Новогоднее оформление сайта» и сохраните — весь список вернётся без потерь.</span>
      </div>
    <?php endif ?>
  </div>
  <div class="card stack" style="gap: 12px">
    <b>Часы работы в праздники</b>
    <span class="small muted">Пишутся в подвале сайта и в блоке «Успеть к празднику». Пусто — не пишем.</span>
    <div class="row" style="gap: 16px; flex-wrap: wrap">
      <label class="stack" style="gap: 6px"><span>31 декабря</span><input class="input" name="hours31" value="<?= e($cfg['hours31']) ?>" placeholder="8:00–18:00" maxlength="40"></label>
      <label class="stack" style="gap: 6px"><span>1 января</span><input class="input" name="hours1" value="<?= e($cfg['hours1']) ?>" placeholder="12:00–21:00" maxlength="40"></label>
    </div>
  </div>
  <div><button class="btn btn-lg">Сохранить</button></div>
</form>
