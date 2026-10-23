<?php
group('интервалы доставки');
$now = strtotime('2026-10-15T08:30:00Z'); // 11:30 по Минску
test('сегодня — только через 2 часа и позже', fn () => eq(Slots::available('2026-10-15', 2, $now), ['14–16', '16–18', '18–21']));
test('завтра — все интервалы', fn () => eq(count(Slots::available('2026-10-16', 2, $now)), 5));
test('вчера — ничего', fn () => eq(Slots::available('2026-10-14', 2, $now), []));
test('проверка интервала при оформлении', function () use ($now) {
    eq(Slots::isSlotAllowed('2026-10-15', '12–14', 2, $now), false);
    eq(Slots::isSlotAllowed('2026-10-15', '14–16', 2, $now), true);
});
test('день без точного времени (адрес уточним у получателя)', function () use ($now) {
    eq(Slots::isDateAllowed('2026-10-15', 2, $now), true);
    eq(Slots::isDateAllowed('2026-10-14', 2, $now), false);
    eq(Slots::isDateAllowed('2026-10-15', 2, strtotime('2026-10-15T17:30:00Z')), false); // 20:30 по Минску
});
test('следующий день', fn () => eq(Slots::addDays('2026-12-31', 1), '2027-01-01'));
