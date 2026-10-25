<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--blush)">
  <div class="card stack" style="background: var(--white); width: 360px; gap: 16px; padding: 28px">
    <div class="logo">Artflora.by</div>
    <div class="muted">Управление сайтом</div>
    <form method="post" action="/admin/login" class="stack" style="gap: 12px">
      <label class="field">
        <span>Пароль</span>
        <input class="input" type="password" name="password" autofocus required>
      </label>
      <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif ?>
      <button class="btn btn-lg">Войти</button>
    </form>
  </div>
</div>
