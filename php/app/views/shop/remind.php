<?php // Напомним о важных датах (src/app/(shop)/remind/page.tsx). Переменные: $ok, $error ?>
<div class="section" style="max-width: 640px">
  <div class="panel stack" style="gap: 16px">
    <h1 class="h2">Напомним о <span class="script">важных датах</span></h1>
    <?php if ($ok): ?>
      <p style="font-size: 18px">Готово! За 5 дней до даты пришлём в Viber подборку букетов и скидку 5%.</p>
    <?php else: ?>
      <form method="post" action="/remind" class="stack" style="gap: 12px">
        <p class="muted">Оставьте дату, за 5 дней пришлём в Viber подборку букетов и скидку 5%.</p>
        <label class="field"><span>Ваше имя</span><input class="input" name="name" maxlength="100" required></label>
        <label class="field"><span>Телефон с Viber</span><input class="input" name="phone" type="tel" value="+375 " required></label>
        <label class="field"><span>Повод</span><input class="input" name="occasion" maxlength="100" placeholder="День рождения мамы" required></label>
        <label class="field"><span>Дата</span><input class="input" name="date" type="date" required></label>
        <?php if ($error !== ''): ?>
          <p class="error"><?= $error === 'busy' ? 'Слишком много заявок подряд, попробуйте позже.' : 'Проверьте поля, что-то заполнено не так.' ?></p>
        <?php endif ?>
        <button class="btn btn-lg" type="submit">Сохранить дату</button>
      </form>
    <?php endif ?>
  </div>
</div>
