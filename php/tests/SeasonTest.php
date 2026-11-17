<?php
group('Новый год: название раздела по дате, счётчик');
$d = fn (string $s) => new DateTimeImmutable($s, new DateTimeZone('Europe/Minsk'));
test('до 25 декабря — «Зимнее настроение», с 25-го — «Новый год»', function () use ($d) {
    eq(Season::phase($d('2026-11-12'), '12-25'), 'winter');
    eq(Season::phase($d('2026-12-24'), '12-25'), 'winter');
    eq(Season::phase($d('2026-12-25'), '12-25'), 'newyear');
    eq(Season::phase($d('2026-12-31'), '12-25'), 'newyear');
    eq(Season::phase($d('2027-01-03'), '12-25'), 'newyear', 'январь — ещё «Новый год»');
    eq(Season::phase($d('2026-12-20'), '12-20'), 'newyear', 'своя дата из админки');
    eq(Season::phase($d('2026-12-20'), 'ерунда'), 'winter', 'испорченная дата → 25 декабря');
});
test('счётчик дней до Нового года', function () use ($d) {
    eq(Season::countdown($d('2026-11-12 15:00')), '50 дней до Нового года');
    eq(Season::countdown($d('2026-12-29')), '3 дня до Нового года');
    eq(Season::countdown($d('2026-12-10')), '22 дня до Нового года');
    eq(Season::countdown($d('2026-12-31 23:00')), 'Завтра Новый год');
    eq(Season::countdown($d('2027-01-02')), '', 'после праздника не показываем');
});
test('склонение', function () {
    eq(Season::plural(1, 'день', 'дня', 'дней'), 'день');
    eq(Season::plural(11, 'день', 'дня', 'дней'), 'дней');
    eq(Season::plural(21, 'день', 'дня', 'дней'), 'день');
    eq(Season::plural(44, 'день', 'дня', 'дней'), 'дня');
    eq(Season::plural(12, 'день', 'дня', 'дней'), 'дней');
});
test('раздел хранится как обычный, его можно отметить в карточке и галочками', function () {
    eq(in_array(Season::SECTION, Occasions::STORED_SECTIONS, true), true);
    eq(isset(Occasions::SECTIONS[Season::SECTION]), true);
    eq(isset(Seo::LANDINGS[Season::WINTER_SLUG], Seo::LANDINGS[Season::NEWYEAR_SLUG]), true);
});
test('у зимнего раздела свой порядок, неизвестные и новые букеты не теряются', function () {
    $list = [['id' => 1], ['id' => 2], ['id' => 3]];
    eq(array_column(Season::orderBouquets($list, [3, 999, 1]), 'id'), [3, 1, 2]);
});
test('при выключенном сезоне зимняя отметка заморожена, остальные разделы редактируются', function () {
    eq(Season::protectSections(['roses'], ['newyear', 'mono'], false), ['roses', 'newyear']);
    eq(Season::protectSections(['roses', 'newyear'], ['mono'], false), ['roses']);
    eq(Season::protectSections(['roses', 'newyear'], [], true), ['roses', 'newyear']);
});
