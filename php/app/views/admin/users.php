<?php // Сотрудники: свои логины, старший администратор может всё, администратор — заказы и каталог ?>
<div class="stack" style="gap: 4px">
  <h1>Сотрудники</h1>
  <span class="muted">
    У каждого свой логин и пароль. <b>Администратор</b> работает с заказами и каталогом (букеты, цветы, коллекции).
    <b>Старший администратор</b> может всё: ещё доставка, настройки и этот раздел.
    Основной пароль из config.php тоже даёт вход старшего администратора (логин не нужен).
  </span>
</div>
<?php if ($msg): ?><p class="<?= $msgOk ? 'small' : 'error' ?>" role="status"><?= e($msg) ?></p><?php endif ?>

<form method="post" action="/admin/users/add" class="card stack" style="gap: 10px; max-width: 640px">
  <?= Auth::csrfField() ?>
  <b>Новый сотрудник</b>
  <div class="row" style="gap: 8px; flex-wrap: wrap">
    <input class="input" name="name" placeholder="Имя, например Ольга" required maxlength="80" style="max-width: 200px">
    <input class="input" name="login" placeholder="Логин латиницей: olga" required pattern="[A-Za-z0-9._\-]{3,40}" autocapitalize="none" style="max-width: 200px">
    <input class="input" name="password" type="text" placeholder="Пароль от 10 символов" required minlength="10" autocomplete="new-password" style="max-width: 200px">
    <select class="input" name="role" style="max-width: 220px">
      <?php foreach (Auth::ROLES as $k => $label): ?><option value="<?= $k ?>" <?= $k === 'staff' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
    </select>
  </div>
  <button class="btn" style="align-self: flex-start">+ Добавить</button>
</form>

<?php if (!$users): ?><p class="muted">Сотрудников пока нет.</p><?php endif ?>
<?php foreach ($users as $u): ?>
  <div class="card stack" style="gap: 10px; max-width: 640px<?= $u['active'] ? '' : '; opacity: .6' ?>">
    <form method="post" action="/admin/users/save" class="stack" style="gap: 10px">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <div class="row between" style="gap: 8px">
        <span>Логин: <b><?= e($u['login']) ?></b></span>
        <span class="small muted"><?= $u['lastLoginAt'] ? 'Последний вход: ' . e(date('d.m.Y H:i', strtotime($u['lastLoginAt']))) : 'Ещё не входил(а)' ?></span>
      </div>
      <div class="row" style="gap: 8px; flex-wrap: wrap">
        <input class="input" name="name" value="<?= e($u['name']) ?>" required maxlength="80" style="max-width: 200px" aria-label="Имя">
        <select class="input" name="role" style="max-width: 220px" aria-label="Роль">
          <?php foreach (Auth::ROLES as $k => $label): ?><option value="<?= $k ?>" <?= $k === $u['role'] ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
        </select>
        <input class="input" name="password" type="text" placeholder="Новый пароль (если меняем)" minlength="10" autocomplete="new-password" style="max-width: 220px" aria-label="Новый пароль">
      </div>
      <label class="row" style="gap: 8px"><input type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?>> Может входить</label>
      <button class="btn btn-outline" style="align-self: flex-start">Сохранить</button>
    </form>
    <form method="post" action="/admin/users/delete" onsubmit="return confirm('Удалить этого сотрудника?')">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <button class="btn-ghost small muted">Удалить</button>
    </form>
  </div>
<?php endforeach ?>
