<?php
// Предзаказ (решение владельца 2026-11-01). Цветы закупаем раз в неделю: закупка — в четверг (крайний срок
// для покупателя — среда 23:59, настройка), цветы приходят во вторник после обеда / в среду. Значит, специально
// под заказ цветы купим только на даты со «дня поставки» (среда) после ближайшей закупки.
// Покупатель выбрал дату раньше — закупка на неё уже сделана: предупреждаем («соберём из того, что есть,
// при необходимости согласуем замену»), и без галочки «Понимаю» заказ не оформить.
// Букеты с галочкой «Предзаказ» (bouquets.isPreorder) видны в разделе /predzakaz, даже если цветов сейчас нет.
final class Preorder
{
    public const URL = '/predzakaz';
    // Цветы приходят не раньше, чем через столько дней после закупки (закупка в четверг → вторник после обеда/среда)
    public const MIN_TRANSIT_DAYS = 5;
    public const DAYS = [1 => 'понедельник', 2 => 'вторник', 3 => 'среда', 4 => 'четверг', 5 => 'пятница', 6 => 'суббота', 7 => 'воскресенье'];
    private const DAYS_ACC = [1 => 'понедельника', 2 => 'вторника', 3 => 'среды', 4 => 'четверга', 5 => 'пятницы', 6 => 'субботы', 7 => 'воскресенья'];
    private const MONTHS = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

    // Настройки из «Настроек» (shop): день и время крайнего срока, день, с которого цветы новой поставки в магазине
    public static function cfg(?array $shop = null): array
    {
        $shop ??= Settings::get('shop');
        $day = (int) ($shop['preorderDeadlineDay'] ?? 3);
        $time = (string) ($shop['preorderDeadlineTime'] ?? '23:59');
        $ready = (int) ($shop['preorderReadyDay'] ?? 3);
        return [
            'deadlineDay' => $day >= 1 && $day <= 7 ? $day : 3,
            'deadlineTime' => preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $t) ? sprintf('%02d:%s', $t[1], $t[2]) : '23:59',
            'readyDay' => $ready >= 1 && $ready <= 7 ? $ready : 3,
        ];
    }

    // Ближайший крайний срок (ещё не прошедший) и первая дата, на которую цветы закупим специально.
    // → ['deadline' => 'YYYY-MM-DD HH:MM', 'from' => 'YYYY-MM-DD']; время минское
    public static function window(?int $now = null, ?array $cfg = null): array
    {
        $cfg ??= self::cfg();
        $m = Slots::minskNow($now);
        $nowHm = sprintf('%02d:%02d', $m['hour'], $m['minute']);
        $dow = (int) gmdate('N', strtotime($m['date'] . ' 00:00:00 UTC'));
        $days = ($cfg['deadlineDay'] - $dow + 7) % 7;
        if ($days === 0 && $nowHm >= $cfg['deadlineTime']) $days = 7; // срок сегодня уже прошёл — следующая неделя
        $deadline = Slots::addDays($m['date'], $days);
        $from = Slots::addDays($deadline, self::MIN_TRANSIT_DAYS);
        $fdow = (int) gmdate('N', strtotime($from . ' 00:00:00 UTC'));
        $from = Slots::addDays($from, ($cfg['readyDay'] - $fdow + 7) % 7);
        return ['deadline' => "$deadline {$cfg['deadlineTime']}", 'from' => $from];
    }

    // Цветы на эту дату закупим специально (true) или закупка уже сделана (false)
    public static function guaranteed(string $date, ?int $now = null, ?array $cfg = null): bool
    {
        return $date >= self::window($now, $cfg)['from'];
    }

    // «11 ноября (среда)»
    public static function dateText(string $iso): string
    {
        $t = strtotime($iso . ' 00:00:00 UTC');
        return (int) gmdate('j', $t) . ' ' . self::MONTHS[(int) gmdate('n', $t)] . ' (' . self::DAYS[(int) gmdate('N', $t)] . ')';
    }

    // «среды 23:59» — для «оформите до …»
    public static function deadlineText(?array $cfg = null): string
    {
        $cfg ??= self::cfg();
        return self::DAYS_ACC[$cfg['deadlineDay']] . ' ' . $cfg['deadlineTime'];
    }

    // Для корзины: ['from', 'fromText', 'deadline', 'deadlineText']
    public static function info(?int $now = null): array
    {
        $cfg = self::cfg();
        $w = self::window($now, $cfg);
        $d = substr($w['deadline'], 0, 10);
        return [
            'from' => $w['from'], 'fromText' => self::dateText($w['from']),
            'deadline' => $w['deadline'], 'deadlineText' => self::dateText($d) . ' до ' . $cfg['deadlineTime'],
            'rule' => 'Цветы закупаем раз в неделю. Оформите до ' . self::deadlineText($cfg) . ' — и мы закупим цветы специально под ваш заказ на даты с ' . self::DAYS_ACC[$cfg['readyDay']] . ' следующей недели.',
        ];
    }

    // Букет-предзаказ: сейчас цветов может не быть — их закупим. Для витрины и корзины считаем всё «в наличии»
    // (все размеры доступны, без замен; цена та же — по цене цветов в справочнике).
    public static function asInStock(array $b): array
    {
        foreach ($b['sizes'] as $si => $s) {
            foreach ($s['items'] as $ii => $i) $b['sizes'][$si]['items'][$ii]['flower']['inStock'] = true;
        }
        return $b;
    }

    // Цена цветов на следующей неделе может отличаться — покупатель выбирает сразу (решение владельца 2026-11-01)
    public const CHOICES = [
        'COPY' => 'Точная копия букета, даже если цена вырастет',
        'BUDGET' => 'Сохранить бюджет: флорист подберёт состав на эту сумму',
    ];

    // Пометка в заказе: BUY — закупить цветы к дате; STOCK — клиент предупреждён, закупка уже сделана
    public static function orderNote(?string $mark, ?string $choice = null): string
    {
        $c = isset(self::CHOICES[$choice]) ? '. Если цена изменится: ' . mb_strtolower(self::CHOICES[$choice]) : '';
        return match ($mark) {
            'BUY' => '📅 Предзаказ: закупить цветы под этот заказ' . $c,
            'STOCK' => '📅 Предзаказ: закупка на эту дату уже сделана, клиент предупреждён (собрать из наличия, замену согласовать)' . $c,
            default => '',
        };
    }
}
