<?php
// Поиск (SEO): адреса разделов каталога, перенаправления со старого сайта, canonical, разметка schema.org.
// Старый сайт artflora.by (Simpla) прочитан 2026-10-24: 43 статьи /blog/…, 51 раздел /catalog/…, 147 букетов /products/….
// Разделы, у которых были позиции в поиске, остаются по старым адресам (LANDINGS), остальные — 301 на похожий раздел.
final class Seo
{
    // Основной домен. На других адресах (test.artflora.by) сайт закрыт от поисковиков, чтобы не было копий.
    public const MAIN_HOST = 'artflora.by';

    // Разделы каталога с понятными адресами: /catalog/<адрес> = каталог с готовым фильтром.
    // h1 — [обычные слова, рукописное слово]. Первые адреса взяты со старого сайта как есть.
    public const LANDINGS = [
        'svadebnye-bukety' => [
            'filter' => ['occasion' => 'wedding'], 'h1' => ['Свадебные', 'букеты'],
            'title' => 'Букет невесты и свадебные букеты в Минске с доставкой',
            'description' => 'Букет невесты и свадебные букеты в Минске: соберём под платье и цветотип, привезём к нужному часу. Салоны в Уручье и Малиновке, фото букета до отправки.',
        ],
        'den-materi' => [
            'filter' => ['occasion' => 'mom'], 'h1' => ['Цветы', 'маме'],
            'title' => 'Цветы маме и на День матери с доставкой по Минску',
            'description' => 'Букеты для мамы и на День матери (14 октября) с доставкой по Минску в день заказа. Пришлём фото букета до отправки, открытка в подарок.',
        ],
        'lyubimoj' => [
            'filter' => ['occasion' => 'love'], 'h1' => ['Цветы', 'любимой'],
            'title' => 'Цветы любимой девушке с доставкой по Минску',
            'description' => 'Букеты любимой: розы, пионовидные розы, нежные сборные букеты. Доставка по Минску в день заказа, фото букета перед отправкой.',
        ],
        'na-den-rozhdeniya' => [
            'filter' => ['occasion' => 'birthday'], 'h1' => ['Цветы на', 'день рождения'],
            'title' => 'Цветы на день рождения с доставкой по Минску',
            'description' => 'Букеты на день рождения и юбилей с доставкой по Минску. Соберём к нужному часу, пришлём фото до отправки, открытка и подкормка в подарок.',
        ],
        'kollege' => [
            'filter' => ['occasion' => 'colleague'], 'h1' => ['Цветы', 'коллеге'],
            'title' => 'Цветы коллеге и руководителю с доставкой по Минску',
            'description' => 'Сдержанные и красивые букеты коллеге, руководителю, учителю. Доставка в офис по Минску, оплата картой, ЕРИП или по ссылке.',
        ],
        'bez-povoda' => [
            'filter' => ['occasion' => 'nothing'], 'h1' => ['Цветы', 'без повода'],
            'title' => 'Букеты без повода с доставкой по Минску',
            'description' => 'Небольшие букеты и знаки внимания без повода. Доставка по Минску в день заказа, самовывоз из салонов в Уручье и Малиновке −10%.',
        ],
        'sbornye-bukety-iz-ekzoticheskih-tsvetov' => [
            'filter' => ['occasion' => 'exotic'], 'h1' => ['Букеты с', 'экзотикой'],
            'title' => 'Сборные букеты из экзотических цветов в Минске',
            'description' => 'Сборные букеты с экзотическими цветами: протеи, орхидеи, калла. Купить в Минске с доставкой на дом, фото букета до отправки.',
        ],
        'kompozitsii-v-shlyapnyh-korobkah' => [
            'filter' => ['format' => 'HATBOX'], 'h1' => ['Цветы в', 'шляпной коробке'],
            'title' => 'Цветы в шляпной коробке купить в Минске с доставкой',
            'description' => 'Композиции из живых цветов в шляпных коробках с доставкой по Минску. Стоят долго, не нужна ваза. Фото до отправки.',
        ],
        'korziny-s-tsvetami' => [
            'filter' => ['format' => 'BASKET'], 'h1' => ['Корзины', 'с цветами'],
            'title' => 'Корзины с цветами купить в Минске с доставкой',
            'description' => 'Цветочные композиции в корзинах с доставкой по Минску: на юбилей, свадьбу, для коллег. Соберём к нужному часу.',
        ],
        'sumochki-s-tsvetami' => [
            'filter' => ['format' => 'BAG'], 'h1' => ['Сумочки', 'с цветами'],
            'title' => 'Сумочки с цветами с доставкой по Минску',
            'description' => 'Цветы в сумочке: удобно нести и дарить, не нужна ваза. Доставка по Минску в день заказа.',
        ],
        'rozy' => [
            'filter' => ['flower' => 'Роза'], 'h1' => ['Букеты из', 'роз'],
            'title' => 'Розы в Минске купить с доставкой: букеты из роз',
            'description' => 'Букеты из роз с доставкой по Минску: эквадорские, кенийские, кустовые и пионовидные розы, 25, 51 и 101 роза. Всегда свежие, фото до отправки.',
        ],
        'gortenzii' => [
            'filter' => ['flower' => 'Гортензия'], 'h1' => ['Букеты с', 'гортензией'],
            'title' => 'Гортензии и букеты с гортензией с доставкой по Минску',
            'description' => 'Букеты с гортензией с доставкой по Минску. Свежие гортензии в салонах «Артфлора» в Уручье и Малиновке.',
        ],
        'alstromeriya-' => [
            'filter' => ['flower' => 'Альстромерия'], 'h1' => ['Букеты с', 'альстромерией'],
            'title' => 'Альстромерия и букеты с альстромерией в Минске',
            'description' => 'Букеты с альстромерией с доставкой по Минску: стоят до двух недель. Фото букета до отправки, самовывоз −10%.',
        ],
        'hrizantemy' => [
            'filter' => ['flower' => 'Хризантема'], 'h1' => ['Букеты с', 'хризантемами'],
            'title' => 'Хризантемы и букеты с хризантемами в Минске',
            'description' => 'Букеты с хризантемами с доставкой по Минску. Кустовые и одноголовые хризантемы, стоят долго.',
        ],
        'buketykompozitsii-do-150-rublej-' => [
            'filter' => ['price' => 'to100'], 'h1' => ['Букеты', 'до 100 BYN'],
            'title' => 'Недорогие букеты до 100 рублей с доставкой по Минску',
            'description' => 'Красивые букеты и композиции до 100 BYN с доставкой по Минску. Пришлём фото букета до отправки.',
        ],
        'buketykompozitsii-ot-150-rublej' => [
            'filter' => ['price' => 'from150'], 'h1' => ['Большие', 'букеты'],
            'title' => 'Большие и премиум букеты от 150 рублей в Минске',
            'description' => 'Большие букеты и премиальные композиции от 150 BYN с доставкой по Минску. Бесплатная доставка от 200 BYN.',
        ],
    ];

    // Старые разделы без пары → похожий новый раздел. Всё остальное из /catalog/… → /catalog.
    public const OLD_CATALOG = [
        'bukety-s-rozami-' => 'rozy', '51roza--ekvador-' => 'rozy', '101-roza-ekvador-' => 'rozy', 'rozy-' => 'rozy',
        'kustovye-rozy-buketykompozitsii-' => 'rozy', 'rozy-k-14-fevralya-' => 'rozy', 'rozy-k-8-marta-' => 'rozy',
        'monobukety' => 'rozy',
        'svadebnye-bukety-do-120-rublej-' => 'svadebnye-bukety', 'svadebnye-bukety-ot-120-do-160-rublej-' => 'svadebnye-bukety',
        'svadebnye-bukety-ot-160-do-300-rublej-' => 'svadebnye-bukety', 'stojkie-svadebnye-bukety-' => 'svadebnye-bukety',
        'svadba' => 'svadebnye-bukety',
        'shlyapnye-korobki-ot-100-do-150-rublej' => 'kompozitsii-v-shlyapnyh-korobkah',
        'shlyapnye-korobki-ot-150-rublej' => 'kompozitsii-v-shlyapnyh-korobkah',
        'shlyapnye-korobki-k-14-fevralya-' => 'kompozitsii-v-shlyapnyh-korobkah',
        'shlyapnye-korobki-k-8-marta-' => 'kompozitsii-v-shlyapnyh-korobkah',
        'korziny-k-14-fevralya-' => 'korziny-s-tsvetami', 'korziny-ot-klassa-' => 'korziny-s-tsvetami',
        'sumochki-k-14-fevralya' => 'sumochki-s-tsvetami',
        'hrizantemy-' => 'hrizantemy',
        'den-rozhdeniya' => 'na-den-rozhdeniya', 'yubilej' => 'na-den-rozhdeniya',
        'svidanie' => 'lyubimoj', 'bukety-k-14-fevralya' => 'lyubimoj', 'bukety-k-8-marta-' => 'lyubimoj',
        'znak-vnimaniya' => 'bez-povoda',
        'vau--bukety-' => 'buketykompozitsii-ot-150-rublej',
    ];

    // Старые страницы магазина → новые адреса
    public const OLD_PAGES = [
        '/dostavka' => '/delivery', '/oplata' => '/delivery', '/obmen-i-vozvrat-tovara-' => '/delivery',
        '/actions' => '/catalog', '/products' => '/catalog', '/catalog/katalog' => '/catalog',
    ];

    // Старая карточка букета: слова в адресе → раздел. Порядок важен: свадебный букет из роз — свадебный.
    public const PRODUCT_WORDS = [
        'svadeb' => 'svadebnye-bukety', 'nevest' => 'svadebnye-bukety',
        'shlyapn' => 'kompozitsii-v-shlyapnyh-korobkah', 'korob' => 'kompozitsii-v-shlyapnyh-korobkah',
        'korzin' => 'korziny-s-tsvetami', 'sumk' => 'sumochki-s-tsvetami', 'sumoch' => 'sumochki-s-tsvetami',
        'gortenz' => 'gortenzii', 'alstromer' => 'alstromeriya-', 'hrizantem' => 'hrizantemy',
        'roz' => 'rozy',
    ];

    public static function isMainSite(): bool
    {
        $host = strtolower((string) parse_url(Config::siteUrl(), PHP_URL_HOST));
        return $host === self::MAIN_HOST || $host === 'www.' . self::MAIN_HOST;
    }

    public static function absUrl(string $path): string
    {
        if (preg_match('#^https?://#', $path)) return $path;
        return Config::siteUrl() . '/' . ltrim($path, '/');
    }

    // Адрес раздела по фильтру каталога, если ровно такой раздел есть: ['occasion' => 'wedding'] → /catalog/svadebnye-bukety
    public static function landingFor(array $filter): ?string
    {
        foreach (self::LANDINGS as $slug => $l) if ($l['filter'] == $filter) return $slug;
        return null;
    }

    // Ссылка на каталог с фильтром: есть такой раздел — его понятный адрес, иначе /catalog?…
    public static function catalogUrl(array $filter): string
    {
        $filter = array_filter($filter, fn ($v) => $v !== null && $v !== '');
        if (!$filter) return '/catalog';
        $slug = self::landingFor($filter);
        return $slug ? "/catalog/$slug" : '/catalog?' . http_build_query($filter);
    }

    // canonical каталога: фильтр «повод / формат / цветок / цена» по одному → адрес раздела;
    // сортировка, «показать ещё» и цвет на адрес не влияют. Поиск и сложные сочетания → null (их не индексируем).
    public static function catalogCanonical(array $sp): ?string
    {
        if (($sp['q'] ?? '') !== '') return null;
        $main = array_intersect_key($sp, array_flip(['occasion', 'format', 'flower', 'price', 'collection']));
        return count($main) > 1 ? null : self::catalogUrl($main);
    }

    // Куда вести старый адрес /catalog/<slug>
    public static function oldCatalogTarget(string $slug): string
    {
        if (isset(self::LANDINGS[$slug])) return "/catalog/$slug";
        return isset(self::OLD_CATALOG[$slug]) ? '/catalog/' . self::OLD_CATALOG[$slug] : '/catalog';
    }

    // Куда вести старый адрес /products/<slug>, если такого букета на новом сайте нет
    public static function oldProductTarget(string $slug): string
    {
        foreach (self::PRODUCT_WORDS as $word => $landing) if (str_contains($slug, $word)) return "/catalog/$landing";
        return '/catalog';
    }

    // <script type="application/ld+json"> для разметки schema.org
    public static function jsonLd(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . "</script>\n";
    }

    // «8:00–21:00» → «08:00», «21:00»
    public static function hours(string $s): ?array
    {
        if (!preg_match('/(\d{1,2})[:.](\d{2})\D+(\d{1,2})[:.](\d{2})/u', $s, $m)) return null;
        return [sprintf('%02d:%s', $m[1], $m[2]), sprintf('%02d:%s', $m[3], $m[4])];
    }

    // Магазин и салоны для поиска и карт (Яндекс, Google): тип Florist = цветочный магазин
    public static function business(array $shop, array $delivery): array
    {
        $site = Config::siteUrl();
        $phone = '+' . preg_replace('/\D/', '', $shop['phone']);
        $graph = [[
            '@type' => 'Organization', '@id' => "$site/#org", 'name' => 'Артфлора', 'url' => "$site/",
            'logo' => self::absUrl('/assets/img/og.jpg'), 'telephone' => $phone,
            'sameAs' => array_values(array_filter([$shop['instagram'] ?? ''])),
        ], [
            '@type' => 'WebSite', '@id' => "$site/#site", 'name' => 'Артфлора', 'url' => "$site/", 'inLanguage' => 'ru',
            'publisher' => ['@id' => "$site/#org"],
            'potentialAction' => ['@type' => 'SearchAction', 'target' => "$site/catalog?q={q}", 'query-input' => 'required name=q'],
        ]];
        foreach ($delivery['shops'] as $s) {
            $h = self::hours($s['hours'] ?? '');
            $graph[] = array_filter([
                '@type' => 'Florist', '@id' => "$site/#shop-{$s['code']}",
                'name' => "Артфлора, {$s['name']}", 'url' => "$site/", 'telephone' => $phone,
                'image' => self::absUrl('/assets/img/og.jpg'), 'priceRange' => 'BYN',
                'parentOrganization' => ['@id' => "$site/#org"],
                'address' => ['@type' => 'PostalAddress', 'streetAddress' => $s['address'], 'addressLocality' => 'Минск', 'addressCountry' => 'BY'],
                'geo' => isset($s['location']) ? ['@type' => 'GeoCoordinates', 'latitude' => $s['location']['lat'], 'longitude' => $s['location']['lng']] : null,
                'hasMap' => $s['mapUrl'] ?? null,
                'openingHoursSpecification' => $h ? [[
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    'opens' => $h[0], 'closes' => $h[1],
                ]] : null,
                'areaServed' => 'Минск',
            ], fn ($v) => $v !== null);
        }
        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    // Букет как товар: цены всех размеров, есть в наличии (скрытые букеты на сайт не попадают)
    public static function product(array $b, string $url): array
    {
        $prices = array_column($b['sizes'], 'priceKop');
        $num = fn (int $kop) => number_format($kop / 100, 2, '.', ''); // schema.org: точка, не запятая
        $offer = count($prices) > 1
            ? ['@type' => 'AggregateOffer', 'lowPrice' => $num(min($prices)), 'highPrice' => $num(max($prices)), 'offerCount' => count($prices)]
            : ['@type' => 'Offer', 'price' => $num($b['priceKop']), 'url' => $url];
        return [
            '@context' => 'https://schema.org', '@type' => 'Product',
            'name' => $b['name'],
            'description' => $b['description'] ?: null,
            'image' => array_map([self::class, 'absUrl'], $b['photos']),
            'brand' => ['@type' => 'Brand', 'name' => 'Артфлора'],
            'offers' => $offer + ['priceCurrency' => 'BYN', 'availability' => 'https://schema.org/InStock', 'seller' => ['@id' => Config::siteUrl() . '/#org']],
        ];
    }

    // Хлебные крошки: [['Главная', '/'], ['Каталог', '/catalog'], ['Букет', null]]
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $path]) {
            $list[] = array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $path !== null ? self::absUrl($path) : null]);
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }
}
