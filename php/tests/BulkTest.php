<?php
group('массовые действия в списке букетов');
$row = ['enabled' => 0, 'isPinned' => 0, 'isHit' => 1, 'sections' => '["roses"]', 'occasions' => '["love"]'];
test('скрыть / показать / ⭐ / хит', function () use ($row) {
    eq(Catalog::bulkPatch($row, 'show'), ['enabled' => 1, 'hiddenByTimerAt' => null], 'включили — пометка таймера снимается');
    eq(Catalog::bulkPatch($row, 'hide'), [], 'уже выключен — менять нечего');
    eq(Catalog::bulkPatch($row, 'pin'), ['isPinned' => 1]);
    eq(Catalog::bulkPatch($row, 'unhit'), ['isHit' => 0]);
});
test('разделы и поводы', function () use ($row) {
    eq(Catalog::bulkPatch($row, 'section-add:wow'), ['sections' => '["roses","wow"]']);
    eq(Catalog::bulkPatch($row, 'section-add:roses'), [], 'уже в разделе');
    eq(Catalog::bulkPatch($row, 'section-remove:roses'), ['sections' => '[]']);
    eq(Catalog::bulkPatch($row, 'section-add:exotic'), ['occasions' => '["love","exotic"]'], '«Экзотика» — это повод');
    eq(Catalog::bulkPatch($row, 'occasion-remove:love'), ['occasions' => '[]']);
    eq(Catalog::bulkPatch($row, 'occasion-add:mom'), ['occasions' => '["love","mom"]']);
});
test('чужие действия не принимаем', function () use ($row) {
    eq(Catalog::bulkPatch($row, 'section-add:xxx'), null);
    eq(Catalog::bulkPatch($row, 'occasion-add:roses'), null);
    eq(Catalog::bulkPatch($row, 'delete'), null);
    eq(Catalog::bulkPatch($row, 'show:1'), null);
});
