<?php
group('интервалы доставки');
$now = strtotime('2026-10-15T08:30:00Z'); // 11:30 по Минску
test('интервалы по часу каждые полчаса, с 9 до 21', function () {
    eq(count(Slots::ALL), 23);
    eq(Slots::ALL[0], '9:00–10:00');
    eq(Slots::ALL[1], '9:30–10:30');
    eq(Slots::ALL[22], '20:00–21:00');
});
test('начало интервала', fn () => eq([Slots::startHour('9:30–10:30'), Slots::startHour('14:00–15:00')], [9.5, 14.0]));
test('сегодня — не раньше чем через час', function () use ($now) {
    $s = Slots::available('2026-10-15', 1, $now);
    eq($s[0], '12:30–13:30');
    eq(count($s), 16);
    eq(Slots::available('2026-10-15', 1, strtotime('2026-10-15T08:40:00Z'))[0], '13:00–14:00'); // 11:40 → с 13:00
});
test('завтра — все интервалы', fn () => eq(count(Slots::available('2026-10-16', 1, $now)), 23));
test('вчера — ничего', fn () => eq(Slots::available('2026-10-14', 2, $now), []));
test('проверка интервала при оформлении', function () use ($now) {
    eq(Slots::isSlotAllowed('2026-10-15', '12:00–13:00', 1, $now), false);
    eq(Slots::isSlotAllowed('2026-10-15', '12:30–13:30', 1, $now), true);
    eq(Slots::isSlotAllowed('2026-10-16', '14–16', 1, $now), false); // старый формат больше не принимаем
});
test('день без точного времени (адрес уточним у получателя)', function () use ($now) {
    eq(Slots::isDateAllowed('2026-10-15', 2, $now), true);
    eq(Slots::isDateAllowed('2026-10-14', 2, $now), false);
    eq(Slots::isDateAllowed('2026-10-15', 1, strtotime('2026-10-15T16:30:00Z')), false); // 19:30 по Минску: 20:00 уже не успеть
});
test('следующий день', fn () => eq(Slots::addDays('2026-12-31', 1), '2027-01-01'));
