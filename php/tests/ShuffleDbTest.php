<?php
group('«Перемешать остальные» (база)');
Db::migrate();
test('перемешиваются только букеты без значков, остальные на местах', function () {
    $shop = Settings::get('shop');
    $ids = [];
    $add = function (string $slug, int $order, array $flags = []) use (&$ids) {
        $ids[$slug] = Db::insert('bouquets', ['name' => "Тест $slug", 'slug' => "test-shuffle-$slug", 'description' => '', 'enabled' => 1, 'sortOrder' => $order,
            'colors' => '[]', 'occasions' => '[]', 'sections' => '[]', 'photos' => '[]'] + $flags);
    };
    try {
        $add('day', -900);
        $add('star', -899, ['isPinned' => 1]);
        $add('hit', -898, ['isHit' => 1]);
        for ($i = 0; $i < 12; $i++) $add("r$i", -897 + $i);
        Settings::save('shop', ['dayBouquet' => 'test-shuffle-day'] + $shop);
        $before = array_map('intval', array_column(Db::all('SELECT id FROM bouquets ORDER BY sortOrder, id'), 'id'));
        $rest = array_map(fn ($i) => $ids["r$i"], range(0, 11));
        $changed = false;
        for ($try = 0; $try < 5 && !$changed; $try++) {
            Catalog::shuffleRest();
            $after = array_map('intval', array_column(Db::all('SELECT id FROM bouquets ORDER BY sortOrder, id'), 'id'));
            $changed = array_values(array_intersect($after, $rest)) !== $rest;
        }
        ok($changed, 'порядок остальных поменялся');
        $pos = array_flip($after);
        $was = array_flip($before);
        foreach (['day', 'star', 'hit'] as $k) eq($pos[$ids[$k]], $was[$ids[$k]], "$k на месте");
        sort($before);
        sort($after);
        eq($after, $before, 'никто не пропал');
    } finally {
        Settings::save('shop', $shop);
        if ($ids) Db::exec('DELETE FROM bouquets WHERE id ' . Db::in(array_values($ids)), array_values($ids));
    }
});
