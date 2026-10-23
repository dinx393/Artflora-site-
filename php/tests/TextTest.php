<?php
group('деньги');
test('целые рубли без копеек', fn () => eq(Money::format(13000), '130 BYN'));
test('копейки через запятую', fn () => eq(Money::format(17250), '172,50 BYN'));
test('разделяет тысячи', fn () => eq(Money::format(125000), '1 250 BYN'));
test('минус для скидки', fn () => eq(Money::format(-1300), '−13 BYN'));
test('читает ввод с запятой и точкой', function () {
    eq(Money::parse('10,5'), 1050);
    eq(Money::parse('6.50'), 650);
    eq(Money::parse('abc'), null);
});
test('процент', fn () => eq(Money::percentOff(13000, 10), 1300));
test('для поля ввода', fn () => eq(Money::toInput(1050), '10,50'));

group('цвета в фильтре каталога');
test('читает несколько цветов, отбрасывает неизвестные и повторы', function () {
    eq(Occasions::parseColors('pink,red,nope,pink'), ['pink', 'red']);
    eq(Occasions::parseColors(null), []);
});
test('добавляет и убирает цвет, порядок как в списке цветов', function () {
    eq(Occasions::toggleColor([], 'red'), 'red');
    eq(Occasions::toggleColor(['red'], 'white'), 'white,red');
    eq(Occasions::toggleColor(['white', 'red'], 'red'), 'white');
    eq(Occasions::toggleColor(['red'], 'red'), null);
});

group('номер в шапке');
$shop = ['phone' => '+375 29 683-28-32', 'telegram' => 'https://t.me/', 'whatsapp' => '', 'viber' => 'viber://chat?number=%2B375296832832'];
test('три мессенджера и звонок', fn () => eq(array_column(Text::contactLinks($shop), 'label'), ['Telegram', 'WhatsApp', 'Viber', 'Позвонить']));
test('пустые ссылки строим по номеру телефона', function () use ($shop) {
    [$tg, $wa, , $call] = Text::contactLinks($shop);
    eq($tg['href'], 'https://t.me/+375296832832');
    eq($wa['href'], 'https://wa.me/375296832832');
    eq($call['href'], 'tel:+375296832832');
});
test('заполненные в настройках ссылки не трогаем', function () use ($shop) {
    [$tg, $wa] = Text::contactLinks(['telegram' => 'https://t.me/artflora_by', 'whatsapp' => 'https://wa.me/375291112233'] + $shop);
    eq($tg['href'], 'https://t.me/artflora_by');
    eq($wa['href'], 'https://wa.me/375291112233');
});

group('адрес для курьера');
test('улица, квартира, подъезд и этаж', fn () => eq(Text::fullAddress(['address' => 'ул. Немига, 5', 'apartment' => '12', 'entrance' => '2', 'floor' => '4']), 'ул. Немига, 5, кв. 12, подъезд 2, этаж 4'));
test('пустые поля пропускает', fn () => eq(Text::fullAddress(['address' => 'пр. Независимости 180 ', 'apartment' => '', 'floor' => ' ']), 'пр. Независимости 180'));
test('район впереди', fn () => eq(Text::fullAddress(['district' => 'Боровляны', 'address' => 'ул. Садовая 5', 'apartment' => '3']), 'Боровляны: ул. Садовая 5, кв. 3'));

group('телефон');
test('белорусские номера к виду +375 29 111-22-33', function () {
    eq(Text::phone('+375291112233'), '+375 29 111-22-33');
    eq(Text::phone('80291112233'), '+375 29 111-22-33');
    eq(Text::phone('+48123456789'), '+48123456789');
});

group('цвета зон');
test('зона по номеру или своя', function () {
    eq(ZoneColors::zone(1), '#4f8ef7');
    eq(ZoneColors::zone(2, '#123456'), '#123456');
    eq(ZoneColors::next(['#4F8EF7']), '#f5892b');
});

group('настройки при запуске');
$strong = ['session_secret' => str_repeat('x', 40), 'admin_password' => 'Очень-длинный-пароль-1'];
test('на своём компьютере можно с паролем по умолчанию', fn () => eq(Config::problems(['site_url' => 'http://localhost:8000', 'admin_password' => 'artflora', 'session_secret' => 'local-only-secret-change-me-on-server']), []));
test('на сервере пароль и ключ по умолчанию не пускают', fn () => eq(count(Config::problems(['site_url' => 'https://artflora.by', 'admin_password' => 'artflora', 'session_secret' => 'local-only-secret-change-me-on-server'])), 2));
test('на сервере нужен https', fn () => eq(count(Config::problems($strong + ['site_url' => 'http://artflora.by'])), 1));
test('правильные настройки сервера', fn () => eq(Config::problems($strong + ['site_url' => 'https://artflora.by']), []));
test('без ключа не запускаемся нигде', fn () => eq(count(Config::problems(['site_url' => 'http://localhost:8000'])), 1));

group('вход и подписи');
test('подпись заказа стабильна и короткая', function () {
    eq(strlen(Auth::orderToken(10001)), 16);
    eq(Auth::orderToken(10001), Auth::orderToken(10001));
    ok(Auth::orderToken(10001) !== Auth::orderToken(10002));
});
test('пароль', function () {
    Config::override(array_merge(Config::all(), ['admin_password' => 'Очень-длинный-пароль-1']));
    eq(Auth::checkPassword('Очень-длинный-пароль-1'), true);
    eq(Auth::checkPassword('нет'), false);
});

group('фото');
test('имя файла и первые байты', function () {
    eq(Uploads::safeName('20261023-ab12.jpg'), '20261023-ab12.jpg');
    eq(Uploads::safeName('../x.php'), null);
    eq(Uploads::looksLikeImage("\xFF\xD8\xFF\xE0", 'image/jpeg'), true);
    eq(Uploads::looksLikeImage('<?php', 'image/jpeg'), false);
});

group('SQL-миграции');
test('файл делится на запросы, комментарии убраны', function () {
    $parts = Db::splitSql(file_get_contents(APP_DIR . '/migrations/001_init.sql'));
    eq(count($parts), 13);
    ok(!str_contains(implode("\n", $parts), '-- '), 'остались комментарии');
});
test('срок «от N часов»', fn () => eq([Text::fromHours(1), Text::fromHours(1.5), Text::fromHours(2), Text::fromHours(11), Text::fromHours(21)], ['от 1 часа', 'от 1,5 часа', 'от 2 часов', 'от 11 часов', 'от 21 часа']));
