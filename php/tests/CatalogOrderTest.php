<?php
group('порядок каталога: Букет дня → ⭐ → хиты → остальные');
$bq = fn (string $slug, array $flags = []) => $flags + ['slug' => $slug, 'isPinned' => false, 'isHit' => false, 'isExclusive' => false];
test('группы', function () use ($bq) {
    eq(Catalog::rank($bq('day', ['isPinned' => true, 'isHit' => true]), 'day'), 0, 'Букет дня выше избранных');
    eq(Catalog::rank($bq('a', ['isPinned' => true, 'isHit' => true]), 'day'), 1);
    eq(Catalog::rank($bq('a', ['isHit' => true]), 'day'), 2);
    eq(Catalog::rank($bq('a', ['isExclusive' => true]), 'day'), 2);
    eq(Catalog::rank($bq('a'), 'day'), 3);
    eq(Catalog::rank($bq('a'), ''), 3, 'Букет дня не выбран');
    eq(Catalog::rank(['slug' => 'a', 'isPinned' => '1', 'isHit' => '0', 'isExclusive' => '0'], ''), 1, 'строки из базы');
});
test('новые (в конце админки) не обгоняют хиты, внутри группы порядок как в админке', function () use ($bq) {
    $list = [$bq('new1'), $bq('hit1', ['isHit' => true]), $bq('new2'), $bq('star', ['isPinned' => true]), $bq('day'), $bq('hit2', ['isExclusive' => true])];
    eq(array_column(Catalog::ranked($list, 'day'), 'slug'), ['day', 'star', 'hit1', 'hit2', 'new1', 'new2']);
});
