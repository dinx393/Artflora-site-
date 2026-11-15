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
<style>.ny-check { display: flex; align-items: flex-start; gap: 10px; } .ny-check input { margin-top: 4px; flex: none; }</style>
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
    <span class="small muted">Букеты в раздел добавляются в карточке букета: «Разделы каталога» → «Зимнее настроение / Новый год», или галочками в списке букетов (массовые действия). Сейчас отмечено: <?= count($marked) ?>.</span>
    <?php if ($marked): ?>
      <div class="chips-wrap">
        <?php foreach ($marked as $b): ?><a href="/admin/bouquets/<?= (int) $b['id'] ?>" class="tag"><?= e($b['name']) ?><?= $b['enabled'] ? '' : ' · выключен' ?></a><?php endforeach ?>
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
