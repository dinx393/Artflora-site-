<?php
// Письмо магазину о новом заказе в цветах сайта. Только разметка, без отправки (отправка — Notify).
//
// Данные письма: number, createdAt (метка времени), status («Новый»), paid, payment («ЕРИП»), customerName, customerPhone,
// recipientName, recipientPhone, callRecipient, anonymous, method (DELIVERY/PICKUP), address, addressUnknown,
// shopName («Уручье, пр. Независимости 168/1»), deliveryDate ('YYYY-MM-DD'), deliverySlot, comment, cardText,
// items [['name','size','qty','priceKop','photo' (полный адрес или null)]], itemsKop, discountKop, deliveryKop, totalKop, adminUrl,
// company (реквизиты для безнала: ['text' => …], или null).
final class OrderEmail
{
    public const STATUS_LABEL = [
        'NEW' => 'Новый', 'CONFIRMED' => 'Собираем', 'PHOTO_SENT' => 'Фото отправлено',
        'ON_THE_WAY' => 'В пути / ждёт', 'DONE' => 'Выполнен', 'CANCELLED' => 'Отменён',
    ];
    public const PAY_LABEL = ['CARD' => 'WebPay (картой по ссылке)', 'ERIP' => 'ЕРИП', 'VIBER_LINK' => 'Ссылкой в Viber', 'INVOICE' => 'Безнал (счёт организации)'];

    private const C = [
        'cherry' => '#7E1F3C', 'ink' => '#3a2a30', 'muted' => '#7a6a72', 'lavender' => '#D6CBE3',
        'lavenderInk' => '#6B4E8C', 'blush' => '#F6F3FA', 'line' => '#E7E0EF', 'green' => '#5E6B32',
    ];
    private const MONTHS = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    private const WEEKDAYS = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    // 17.10.2026, 09:16 по Минску
    public static function dateTime(int $ts): string
    {
        return gmdate('d.m.Y, H:i', $ts + 3 * 3600);
    }

    // «суббота, 17 октября»
    public static function day(string $isoDate): string
    {
        $t = strtotime("$isoDate 00:00:00 UTC");
        return self::WEEKDAYS[(int) gmdate('w', $t)] . ', ' . (int) gmdate('j', $t) . ' ' . self::MONTHS[(int) gmdate('n', $t) - 1];
    }

    private static function deliveryLine(array $o): string
    {
        if ($o['method'] === 'PICKUP') return "Самовывоз: {$o['shopName']}";
        if ($o['addressUnknown']) return 'Адрес и время уточнить у получателя, доплата за доставку отдельно';
        return $o['address'];
    }

    private static function rows(array $o): array
    {
        $r = [
            ['Статус', $o['status']],
            ['Оплата', ($o['paid'] ? 'оплачен' : 'не оплачен') . ' · ' . $o['payment']],
            ['Имя', $o['customerName']],
            ['Телефон', Text::phone($o['customerPhone'])],
        ];
        if (!empty($o['company']['text'])) $r[] = ['Реквизиты для счёта', $o['company']['text']];
        if ($o['method'] === 'DELIVERY') {
            $r[] = ['Получатель', $o['recipientName'] ?: '—'];
            if ($o['recipientPhone']) $r[] = ['Телефон получателя', Text::phone($o['recipientPhone'])];
        }
        $r[] = [$o['method'] === 'PICKUP' ? 'Самовывоз' : 'Адрес доставки', self::deliveryLine($o)];
        $r[] = ['Когда', self::day($o['deliveryDate']) . ', ' . ($o['deliverySlot'] ?: 'время уточнить')];
        $notes = array_filter([$o['callRecipient'] ? 'позвонить получателю перед доставкой' : '', $o['anonymous'] ? 'не говорить, от кого букет' : '']);
        if ($notes) $r[] = ['Важно', implode('; ', $notes)];
        $r[] = ['Открытка', $o['cardText'] !== '' ? "«{$o['cardText']}»" : 'без текста'];
        if ($o['comment'] !== '') $r[] = ['Комментарий', $o['comment']];
        $r[] = ['Дата заказа', self::dateTime($o['createdAt'])];
        return $r;
    }

    // ['subject' => …, 'html' => …, 'text' => …]
    public static function render(array $o): array
    {
        $C = self::C;
        $total = Money::format($o['totalKop']);
        $state = $o['paid'] ? 'оплачен' : 'ещё не оплачен, ждёт обработки';
        $subject = "Заказ №{$o['number']} на $total — " . ($o['paid'] ? 'оплачен' : 'ждёт обработки');

        $td = "padding:12px 14px;border-bottom:1px solid {$C['line']};vertical-align:top;font-size:15px;line-height:1.45";
        $info = '';
        foreach (self::rows($o) as [$k, $v]) {
            $info .= "<tr><td style=\"$td;width:38%;color:{$C['muted']}\">" . self::esc($k) . "</td><td style=\"$td;color:{$C['ink']}\">" . str_replace("\n", '<br>', self::esc($v)) . '</td></tr>';
        }

        $items = '';
        foreach ($o['items'] as $i) {
            $img = $i['photo']
                ? '<img src="' . self::esc($i['photo']) . "\" width=\"64\" height=\"64\" alt=\"\" style=\"display:block;width:64px;height:64px;object-fit:cover;border-radius:12px;background:{$C['blush']}\">"
                : "<div style=\"width:64px;height:64px;border-radius:12px;background:{$C['blush']}\"></div>";
            $size = $i['size'] ? "<div style=\"color:{$C['muted']};font-size:14px\">Размер " . self::esc($i['size']) . '</div>' : '';
            $items .= "<tr>
        <td style=\"$td;width:64px;padding-right:0\">$img</td>
        <td style=\"$td\"><div style=\"font-weight:700;color:{$C['ink']}\">" . self::esc($i['name']) . "</div>$size</td>
        <td style=\"$td;text-align:right;white-space:nowrap;color:{$C['ink']}\">{$i['qty']} шт × " . Money::format($i['priceKop']) . '</td>
      </tr>';
        }

        $sum = function (string $k, string $v, bool $strong = false) use ($C): string {
            $extra = $strong ? 'font-weight:700;font-size:17px' : '';
            return "<tr><td style=\"padding:6px 0;font-size:15px;color:" . ($strong ? $C['ink'] : $C['muted']) . ";$extra\">$k</td><td style=\"padding:6px 0;text-align:right;font-size:15px;color:{$C['ink']};$extra\">$v</td></tr>";
        };
        $deliveryText = $o['method'] === 'PICKUP' ? 'самовывоз'
            : ($o['addressUnknown'] ? 'доплатой после уточнения'
            : ($o['deliveryKop'] === 0 ? "<span style=\"color:{$C['green']}\">бесплатно</span>" : Money::format($o['deliveryKop'])));
        $sums = $sum('Букеты и дополнения', Money::format($o['itemsKop']))
            . ($o['discountKop'] ? $sum('Скидка за самовывоз', '−' . Money::format($o['discountKop'])) : '')
            . $sum('Доставка', $deliveryText)
            . $sum('Способ оплаты', self::esc($o['payment']))
            . $sum('Итого', $total, true);

        $admin = self::esc($o['adminUrl']);
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . self::esc($subject) . "</title></head>
<body style=\"margin:0;padding:0;background:#ffffff;font-family:Manrope,'Segoe UI',Arial,sans-serif;color:{$C['ink']}\">
<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#ffffff\"><tr><td align=\"center\" style=\"padding:24px 12px\">
<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px\">
  <tr><td style=\"height:6px;background:{$C['lavender']};border-radius:6px 6px 0 0\"></td></tr>
  <tr><td style=\"padding:22px 4px 6px\">
    <div style=\"font-family:'Playfair Display',Georgia,serif;font-size:26px;color:{$C['cherry']};letter-spacing:.5px\">Артфлора.by</div>
  </td></tr>
  <tr><td style=\"padding:8px 4px 18px\">
    <div style=\"font-family:'Playfair Display',Georgia,serif;font-size:32px;line-height:1.15;color:{$C['ink']}\">
      <a href=\"$admin\" style=\"color:{$C['cherry']};text-decoration:underline\">Заказ №{$o['number']}</a> на сумму $total
      <span style=\"font-style:italic;color:{$C['lavenderInk']}\">$state</span>
    </div>
  </td></tr>
  <tr><td><table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:1px solid {$C['line']};border-radius:14px;border-collapse:separate;overflow:hidden\">$info</table></td></tr>
  <tr><td style=\"padding:28px 4px 10px;font-family:'Playfair Display',Georgia,serif;font-size:26px;color:{$C['ink']}\">Покупатель заказал</td></tr>
  <tr><td><table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:1px solid {$C['line']};border-radius:14px;border-collapse:separate;overflow:hidden\">$items</table></td></tr>
  <tr><td style=\"height:10px;line-height:10px;font-size:0\">&nbsp;</td></tr>
  <tr><td style=\"padding:12px 14px;background:{$C['blush']};border-radius:14px\"><table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">$sums</table></td></tr>
  <tr><td align=\"center\" style=\"padding:26px 0 8px\">
    <a href=\"$admin\" style=\"display:inline-block;background:{$C['cherry']};color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:999px\">Открыть заказ в админке</a>
  </td></tr>
  <tr><td style=\"padding:22px 4px 0;border-top:1px solid {$C['line']};color:{$C['muted']};font-size:13px;text-align:center\">Письмо отправлено сайтом автоматически</td></tr>
</table></td></tr></table></body></html>";

        $lines = [
            "Заказ №{$o['number']} на сумму $total $state",
            '',
            ...array_map(fn ($r) => "{$r[0]}: {$r[1]}", self::rows($o)),
            '',
            'Покупатель заказал:',
            ...array_map(fn ($i) => "• {$i['name']}" . ($i['size'] ? ", размер {$i['size']}" : '') . " — {$i['qty']} шт × " . Money::format($i['priceKop']), $o['items']),
            $o['discountKop'] ? 'Скидка: −' . Money::format($o['discountKop']) : '',
            'Доставка: ' . strip_tags($deliveryText),
            "Итого: $total",
            '',
            $o['adminUrl'],
        ];
        $text = [];
        foreach ($lines as $i => $l) if ($l !== '' || ($lines[$i - 1] ?? '') !== '') $text[] = $l;
        return ['subject' => $subject, 'html' => $html, 'text' => implode("\n", $text)];
    }
}
