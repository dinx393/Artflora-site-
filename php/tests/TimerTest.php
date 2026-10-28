<?php
group('таймер «собран сегодня»');
test('сколько осталось — коротко', function () {
    $now = strtotime('2026-10-28 12:00:00');
    eq(Catalog::timerLeft('2026-10-30 00:00:00', $now), '1 д 12 ч');
    eq(Catalog::timerLeft('2026-10-29 12:00:00', $now), '1 д');
    eq(Catalog::timerLeft('2026-10-28 17:20:00', $now), '5 ч 20 мин');
    eq(Catalog::timerLeft('2026-10-28 12:00:30', $now), '1 мин');
    eq(Catalog::timerLeft('2026-10-28 11:00:00', $now), '1 мин', 'срок уже прошёл');
    eq(Catalog::timerDate('2026-10-29 09:05:00'), '29.10 в 09:05');
});
