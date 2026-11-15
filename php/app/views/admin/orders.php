<?php // Заказы = src/app/admin/(panel)/orders/page.tsx
$shopNames = array_column(Settings::get('delivery')['shops'], 'name', 'code'); ?>
<h1>Заказы</h1>
<?php if ($unnotified > 0): ?>
  <p class="error">Уведомление в Telegram не дошло по <?= $unnotified ?> заказ(ам). Сайт повторяет отправку сам, но проверьте новые заказы здесь.</p>
<?php endif ?>
<?php if ($unemailed > 0): ?>
  <p class="error">Письмо о заказе не ушло на почту по <?= $unemailed ?> заказ(ам). Сайт повторяет отправку сам; если не проходит, проверьте настройки почты.</p>
<?php endif ?>
<div class="tabs">
  <a href="/admin/orders" class="chip <?= $active ? 'on' : '' ?>">В работе</a>
  <?php foreach (ADMIN_STATUS_LABEL as $k => $label): ?>
    <a href="/admin/orders?status=<?= $k ?>" class="chip <?= $status === $k ? 'on' : '' ?>"><?= e($label) ?> · <?= $counts[$k] ?? 0 ?></a>
  <?php endforeach ?>
  <a href="/admin/orders?status=all" class="chip <?= $status === 'all' ? 'on' : '' ?>">Все</a>
</div>
<?php if (!$orders): ?>
  <p class="muted">Заказов нет.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>№</th><th>Когда</th><th>Что</th><th>Куда</th><th>Клиент</th><th>Сумма</th><th>Статус</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><a href="/admin/orders/<?= $o['id'] ?>" style="font-weight: 800"><?= e($o['number']) ?></a></td>
            <td class="small"><?= e(admin_date($o['deliveryDate'])) ?>, <?= e($o['deliverySlot'] ?: 'время ?') ?></td>
            <td class="small"><?= e(implode(', ', array_map(fn ($i) => $i['name'] . ($i['size'] ? " {$i['size']}" : '') . ($i['qty'] > 1 ? " ×{$i['qty']}" : ''), $o['items']))) ?></td>
            <td class="small"><?= e($o['deliveryMethod'] === 'PICKUP' ? 'Самовывоз · ' . ($shopNames[$o['shop']] ?? $o['shop']) : ($o['address'] ?: ($o['addressUnknown'] ? 'Уточнить у получателя' : ''))) ?></td>
            <td class="small"><?= e($o['customerName']) ?><br><?= e(Text::phone($o['customerPhone'])) ?><?php if (!empty($o['company'])): ?><br><b>Безнал: <?= e(Checkout::companyTitle(Db::fromJson($o['company'], []))) ?></b><?php endif ?></td>
            <td style="font-weight: 800"><?= e(Money::format($o['totalKop'])) ?><?php if ($o['paid']): ?><div class="pill ok">оплачен</div><?php endif ?></td>
            <td>
              <select class="st-pick st-<?= e($o['status']) ?>" data-id="<?= (int) $o['id'] ?>" data-was="<?= e($o['status']) ?>" aria-label="Статус заказа <?= e($o['number']) ?>">
                <?php foreach (ADMIN_STATUS_LABEL as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
              </select>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<style>
  /* Статус меняется прямо в списке: значок — это выпадающий список */
  .st-pick { appearance: none; -webkit-appearance: none; border: 1px solid transparent; border-radius: 999px; padding: 5px 26px 5px 12px; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
    background: var(--blush) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%237e1f3c' stroke-width='1.6'/%3E%3C/svg%3E") no-repeat right 10px center; color: var(--cherry); }
  .st-pick:hover, .st-pick:focus { border-color: var(--cherry); outline: none; }
  .st-pick.st-NEW { background-color: #fdebd0; color: #8a4b00; }
  .st-pick.st-DONE { background-color: #eef1e4; color: var(--olive); }
  .st-pick.st-CANCELLED { background-color: #eee; color: #777; }
  .st-pick.busy { opacity: .5; }
  .st-msg { display: block; font-size: 12px; margin-top: 3px; }
</style>
<script>
document.querySelectorAll('.st-pick').forEach(function (sel) {
  sel.addEventListener('change', function () {
    var was = sel.dataset.was, now = sel.value, note = sel.parentNode.querySelector('.st-msg');
    if (!note) { note = document.createElement('span'); note.className = 'st-msg'; sel.parentNode.appendChild(note); }
    var body = new FormData();
    body.append('_csrf', <?= json_encode(Auth::csrfToken()) ?>);
    body.append('id', sel.dataset.id); body.append('status', now); body.append('ajax', '1');
    sel.classList.add('busy'); sel.disabled = true;
    fetch('/admin/orders/update', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) {
        if (!d.ok || d.status !== now) throw 0;
        sel.className = 'st-pick st-' + now; sel.dataset.was = now;
        note.textContent = '✓ сохранено'; note.style.color = 'var(--olive)';
      })
      .catch(function () { sel.value = was; note.textContent = 'Не сохранилось, попробуйте ещё раз'; note.style.color = 'var(--danger)'; })
      .finally(function () { sel.classList.remove('busy'); sel.disabled = false; setTimeout(function () { if (note.textContent.charAt(0) === '✓') note.textContent = ''; }, 2500); });
  });
});
</script>
