<?php
// Корзина и оформление заказа на сервере (повторяет src/lib/checkout.ts).
// Цены из браузера не используем никогда: браузер присылает только id букетов, размеры и количество,
// всё остальное считаем здесь по текущим ценам из базы.
final class Checkout
{
    public const PAYMENTS = ['CARD', 'ERIP', 'VIBER_LINK', 'INVOICE'];
    // Реквизиты для безнала (INVOICE): поле => [подпись, длина]. E-mail необязателен, остальное нужно для счёта.
    public const COMPANY_FIELDS = [
        'name' => ['Организация', 200], 'unp' => ['УНП', 9], 'address' => ['Юридический адрес', 300],
        'iban' => ['Расчётный счёт', 34], 'bank' => ['Банк', 200], 'bic' => ['БИК', 11], 'email' => ['E-mail для счёта', 100],
    ];

    // Корзина из браузера → ['lines' => [['bouquetId','size','qty']], 'addons' => [['addonId','qty']], 'cardText'].
    // Что-то не так (не те типы, слишком много строк) — null.
    public static function parseCart(mixed $c): ?array
    {
        if (!is_array($c) || !is_array($c['lines'] ?? null) || !is_array($c['addons'] ?? [])) return null;
        $text = $c['cardText'] ?? '';
        if (!is_string($text) || mb_strlen($text) > 500) return null;
        if (count($c['lines']) > 30 || count($c['addons'] ?? []) > 20) return null;
        $lines = [];
        foreach ($c['lines'] as $l) {
            if (!is_array($l) || !is_int($l['bouquetId'] ?? null) || !in_array($l['size'] ?? null, Pricing::SIZE_ORDER, true)) return null;
            if (!is_int($l['qty'] ?? null) || $l['qty'] < 1 || $l['qty'] > 20) return null;
            $lines[] = ['bouquetId' => $l['bouquetId'], 'size' => $l['size'], 'qty' => $l['qty']];
        }
        $addons = [];
        foreach ($c['addons'] ?? [] as $a) {
            if (!is_array($a) || !is_int($a['addonId'] ?? null) || !is_int($a['qty'] ?? null) || $a['qty'] < 1 || $a['qty'] > 10) return null;
            $addons[] = ['addonId' => $a['addonId'], 'qty' => $a['qty']];
        }
        return ['lines' => $lines, 'addons' => $addons, 'cardText' => $text];
    }

    // Пересчитываем корзину по текущим ценам из базы.
    // → ['lines' => [['bouquetId','slug','name','photo','size','sizes' => [['code','priceKop']],'qty','priceKop','composition']],
    //    'addons' => [['addonId','name','qty','priceKop']], 'removed' => [что пропало из наличия], 'bouquetsKop', 'addonsKop']
    public static function priceCart(array $cart): array
    {
        $ids = array_values(array_unique(array_column($cart['lines'], 'bouquetId')));
        $rows = [];
        foreach ($ids ? Catalog::load('b.id ' . Db::in($ids), $ids) : [] as $b) $rows[$b['id']] = $b;
        $lines = [];
        $removed = [];
        foreach ($cart['lines'] as $l) {
            $b = $rows[$l['bouquetId']] ?? null;
            if (!$b || !Pricing::visibility($b)['visible']) {
                $removed[] = $b['name'] ?? 'Букет';
                continue;
            }
            $size = null;
            foreach ($b['sizes'] as $s) if ($s['code'] === $l['size']) $size = $s;
            if (!$size || !Pricing::sizeAvailable($size)) {
                $removed[] = "{$b['name']}, {$l['size']}";
                continue;
            }
            $lines[] = [
                'bouquetId' => $b['id'],
                'slug' => $b['slug'],
                'name' => $b['name'],
                'photo' => $b['photos'][0] ?? Catalog::PLACEHOLDER,
                'size' => $size['code'],
                // размеры по порядку: XS, S, M, L, XL, XXL
                'sizes' => array_map(fn ($s) => ['code' => $s['code'], 'priceKop' => Pricing::sizePriceKop($s)], Pricing::availableSizes($b)),
                'qty' => max(1, min(20, (int) $l['qty'])),
                'priceKop' => Pricing::sizePriceKop($size),
                'composition' => Pricing::compositionText($size['items']),
            ];
        }

        $addonIds = array_column($cart['addons'], 'addonId');
        $addonRows = [];
        if ($addonIds) {
            foreach (Db::all('SELECT id, name, priceKop FROM addons WHERE active = 1 AND included = 0 AND id ' . Db::in($addonIds), $addonIds) as $r) {
                $addonRows[(int) $r['id']] = $r;
            }
        }
        $addons = [];
        foreach ($cart['addons'] as $a) {
            $r = $addonRows[$a['addonId']] ?? null;
            if ($r) $addons[] = ['addonId' => (int) $r['id'], 'name' => $r['name'], 'qty' => max(1, min(10, $a['qty'])), 'priceKop' => (int) $r['priceKop']];
        }

        return [
            'lines' => $lines,
            'addons' => $addons,
            'removed' => $removed,
            'bouquetsKop' => array_sum(array_map(fn ($l) => $l['priceKop'] * $l['qty'], $lines)),
            'addonsKop' => array_sum(array_map(fn ($a) => $a['priceKop'] * $a['qty'], $addons)),
        ];
    }

    // Итог корзины: ['priced', 'quote' (null при самовывозе или без выбора места), 'discountKop', 'deliveryKop', 'totalKop']
    // $place — см. Delivery::quotePlace (Checkout::parsePlace)
    public static function totals(array $cart, string $method, ?array $place): array
    {
        $cfg = Settings::get('delivery');
        $priced = self::priceCart($cart);
        $goods = $priced['bouquetsKop'] + $priced['addonsKop'];
        $quote = null;
        $discountKop = 0;
        $deliveryKop = 0;
        if ($method === 'PICKUP') {
            $discountKop = Delivery::pickupDiscountKop($priced['bouquetsKop'], $cfg);
        } elseif ($place) {
            $quote = Delivery::quotePlace($place, $priced['bouquetsKop'], $cfg);
            if ($quote['ok']) $deliveryKop = $quote['priceKop'];
        }
        return ['priced' => $priced, 'quote' => $quote, 'discountKop' => $discountKop, 'deliveryKop' => $deliveryKop, 'totalKop' => $goods - $discountKop + $deliveryKop];
    }

    // Куда везти — из браузера: место на карте, кнопка зоны или участка. Старые страницы присылают
    // район по названию — его тоже понимаем. Цену всё равно считает сервер. Не понятно — null.
    public static function parsePlace(mixed $raw, mixed $district = ''): ?array
    {
        if (is_array($raw)) {
            $type = $raw['type'] ?? null;
            $num = fn ($v) => is_int($v) || is_float($v);
            if ($type === 'point' && $num($raw['lat'] ?? null) && $num($raw['lng'] ?? null)
                && $raw['lat'] > 50 && $raw['lat'] < 57 && $raw['lng'] > 22 && $raw['lng'] < 33) {
                return ['type' => 'point', 'lat' => round((float) $raw['lat'], 5), 'lng' => round((float) $raw['lng'], 5)];
            }
            if ($type === 'zone' && is_int($raw['zone'] ?? null) && $raw['zone'] >= 1 && $raw['zone'] <= 50) return ['type' => 'zone', 'zone' => $raw['zone']];
            if ($type === 'outside') return ['type' => 'outside'];
            if ($type === 'area' && is_string($raw['name'] ?? null) && $raw['name'] !== '' && mb_strlen($raw['name']) <= 80) return ['type' => 'area', 'name' => $raw['name']];
            return null;
        }
        if (is_string($district) && $district !== '' && ($p = self::districtPoint($district))) return ['type' => 'point', 'district' => $district] + $p;
        return null;
    }

    // Точка района из списка в настройках или своего участка с карты; нет такого — null.
    public static function districtPoint(string $name): ?array
    {
        $districts = Settings::get('districts');
        foreach ([...$districts, ...Delivery::areaPlaces(Settings::get('delivery'), $districts)] as $d) {
            if ($d['name'] === $name) return ['lat' => (float) $d['lat'], 'lng' => (float) $d['lng']];
        }
        return null;
    }

    // Проверка заявки из браузера (как orderSchema в checkout.ts).
    // Удачно — ['ok' => true, 'order' => чистые данные]; иначе ['ok' => false, 'error', 'field'].
    public static function validate(mixed $raw): array
    {
        $bad = fn (string $error, string $field = '') => ['ok' => false, 'error' => $error, 'field' => $field];
        if (!is_array($raw)) return $bad('Не получилось прочитать заказ, обновите страницу');
        $bool = fn (string $key) => ($raw[$key] ?? false) === true;

        $cart = self::parseCart($raw['cart'] ?? null);
        if ($cart === null) return $bad('Корзина устарела, обновите страницу', 'cart');
        if (!$cart['lines']) return $bad('Корзина пуста', 'cart.lines');
        $method = $raw['method'] ?? null;
        if (!in_array($method, ['DELIVERY', 'PICKUP'], true)) return $bad('Выберите доставку или самовывоз', 'method');

        $fields = ['address' => 300, 'district' => 40, 'apartment' => 20, 'entrance' => 20, 'floor' => 20, 'shop' => 40,
            'slot' => 20, 'customerName' => 100, 'recipientName' => 100, 'recipientPhone' => 30, 'comment' => 1000, 'date' => 10, 'customerPhone' => 40];
        // Имена, район и части адреса — без пробелов по краям (как .trim() в схеме)
        $trimmed = ['district', 'apartment', 'entrance', 'floor', 'customerName', 'recipientName'];
        $o = ['cart' => $cart, 'method' => $method];
        foreach ($fields as $k => $max) {
            $v = $raw[$k] ?? '';
            if (!is_string($v)) return $bad('Обновите страницу и попробуйте ещё раз', $k);
            if (in_array($k, $trimmed, true)) $v = trim($v);
            if (mb_strlen($v) > $max) return $bad('Слишком длинный текст, сократите', $k);
            $o[$k] = $v;
        }
        foreach (['addressUnknown', 'callRecipient', 'anonymous'] as $k) $o[$k] = $bool($k);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $o['date'])) return $bad('Выберите дату', 'date');
        if ($o['customerName'] === '') return $bad('Укажите ваше имя', 'customerName');
        $o['customerPhone'] = preg_replace('/[^\d+]/', '', $o['customerPhone']);
        if (!preg_match('/^\+?\d{9,13}$/', $o['customerPhone'])) return $bad('Проверьте номер телефона', 'customerPhone');
        if (!in_array($raw['payment'] ?? null, self::PAYMENTS, true)) return $bad('Выберите способ оплаты', 'payment');
        $o['payment'] = $raw['payment'];
        $o['company'] = null;
        if ($o['payment'] === 'INVOICE') {
            $c = self::parseCompany($raw['company'] ?? null);
            if (!$c['ok']) return $c;
            $o['company'] = $c['company'];
        }
        // Согласие на обработку персональных данных (закон № 99-З), галочка в корзине. Сохранять отдельно не нужно: без него заказа нет.
        if (!$bool('consent')) return $bad('Отметьте согласие на обработку персональных данных', 'consent');

        // Один ключ на одно оформление: повтор запроса (двойной клик, обрыв связи) вернёт уже созданный заказ.
        $key = $raw['idempotencyKey'] ?? null;
        if ($key !== null && (!is_string($key) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key))) {
            return $bad('Обновите страницу и попробуйте ещё раз');
        }
        $o['idempotencyKey'] = $key;

        if ($method === 'DELIVERY' && !$o['addressUnknown'] && mb_strlen(trim($o['address'])) <= 3) return $bad('Укажите адрес доставки', 'address');
        if ($method === 'DELIVERY' && $o['addressUnknown'] && strlen(preg_replace('/\D/', '', $o['recipientPhone'])) < 9) {
            return $bad('Укажите телефон получателя: по нему уточним адрес', 'recipientPhone');
        }
        return ['ok' => true, 'order' => $o];
    }

    // Реквизиты организации для безнала (как в счёте по практике РБ): название, УНП (9 цифр), юр. адрес,
    // расчётный счёт IBAN (BY…, 28 знаков, с проверкой контрольных цифр), банк, БИК; e-mail — по желанию.
    // → ['ok' => true, 'company' => […]] | ['ok' => false, 'error', 'field' => 'company.<поле>']
    public static function parseCompany(mixed $raw): array
    {
        $bad = fn (string $error, string $k) => ['ok' => false, 'error' => $error, 'field' => "company.$k"];
        if (!is_array($raw)) return $bad('Укажите реквизиты организации', 'name');
        $c = [];
        foreach (self::COMPANY_FIELDS as $k => [$label, $max]) {
            $v = $raw[$k] ?? '';
            if (!is_string($v)) return $bad('Обновите страницу и попробуйте ещё раз', $k);
            $v = trim(preg_replace('/\s+/u', ' ', $v));
            // В номерах пробелы и регистр не важны: «by13 nbrb 3600…» → «BY13NBRB3600…»
            if (in_array($k, ['unp', 'iban', 'bic'], true)) $v = mb_strtoupper(str_replace(' ', '', $v));
            if (mb_strlen($v) > max($max, 40)) return $bad('Слишком длинный текст, сократите', $k);
            $c[$k] = $v;
        }
        if (mb_strlen($c['name']) < 2) return $bad('Укажите название организации', 'name');
        if (!preg_match('/^\d{9}$/', $c['unp'])) return $bad('УНП — это 9 цифр', 'unp');
        if (mb_strlen($c['address']) < 5) return $bad('Укажите юридический адрес организации', 'address');
        if (!self::ibanOk($c['iban'])) return $bad('Проверьте расчётный счёт: BY и 26 знаков, как в выписке банка', 'iban');
        if (mb_strlen($c['bank']) < 2) return $bad('Укажите банк', 'bank');
        if (!preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $c['bic'])) return $bad('Проверьте БИК банка: 8 или 11 латинских букв и цифр, например AKBBBY2X', 'bic');
        if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) return $bad('Проверьте e-mail для счёта', 'email');
        return ['ok' => true, 'company' => $c];
    }

    // Белорусский IBAN: BY, 2 контрольные цифры, 4 буквы банка, 4 цифры балансового счёта, 16 знаков; остаток от деления на 97 равен 1.
    public static function ibanOk(string $iban): bool
    {
        if (!preg_match('/^BY\d{2}[A-Z0-9]{4}\d{4}[A-Z0-9]{16}$/', $iban)) return false;
        $digits = '';
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $ch) $digits .= ctype_digit($ch) ? $ch : (string) (ord($ch) - 55);
        $rest = 0;
        foreach (str_split($digits, 7) as $part) $rest = (int) ($rest . $part) % 97;
        return $rest === 1;
    }

    // Реквизиты одной строкой на поле — для админки, письма и Telegram: [['Организация', 'ООО «Ромашка»'], …]
    public static function companyRows(?array $c): array
    {
        $rows = [];
        foreach (self::COMPANY_FIELDS as $k => [$label]) if (($c[$k] ?? '') !== '') $rows[] = [$label, $c[$k]];
        return $rows;
    }

    // Создать заказ. → ['ok' => true, 'number', 'token', 'id', 'new' (false — это повтор уже созданного)]
    //                 | ['ok' => false, 'error', 'field'?, 'removed'?]
    public static function createOrder(mixed $raw, ?int $now = null): array
    {
        $v = self::validate($raw);
        if (!$v['ok']) return $v;
        $o = $v['order'];
        if ($o['idempotencyKey'] && ($existing = self::byKey($o['idempotencyKey']))) return $existing;

        $shopSettings = Settings::get('shop');
        $cfg = Settings::get('delivery');
        $unknown = $o['method'] === 'DELIVERY' && $o['addressUnknown'];
        $lead = $shopSettings['leadHours'];
        if ($unknown ? !Slots::isDateAllowed($o['date'], $lead, $now) : !Slots::isSlotAllowed($o['date'], $o['slot'], $lead, $now)) {
            return ['ok' => false, 'error' => $unknown ? 'На этот день уже не успеваем, выберите другой' : 'Это время уже недоступно, выберите другое', 'field' => 'slot'];
        }

        // Доставку считаем по месту, которое покупатель выбрал на карте или кнопкой зоны (решение владельца 2026-10-23).
        // Выбор и адрес вместе видны в заказе: при подтверждении их сверяют, а оплату покупатель вносит
        // только после подтверждения.
        $place = null;
        if ($o['method'] === 'DELIVERY' && !$unknown) {
            $place = self::parsePlace($raw['place'] ?? null, $o['district']);
            if (!$place) return ['ok' => false, 'error' => 'Выберите на карте, куда везти', 'field' => 'address'];
        }

        $t = self::totals($o['cart'], $o['method'], $place);
        if ($place && ($t['quote']['ok'] ?? false)) $o['district'] = Delivery::placeLabel($place, $t['quote'], $cfg);
        if ($t['priced']['removed']) {
            return ['ok' => false, 'error' => 'Часть букетов закончилась, корзина обновлена. Проверьте заказ ещё раз', 'removed' => $t['priced']['removed']];
        }
        if (!$t['priced']['lines']) return ['ok' => false, 'error' => 'Корзина пуста'];
        if ($o['method'] === 'DELIVERY' && !$unknown && !($t['quote']['ok'] ?? false)) {
            return ['ok' => false, 'error' => "Мы возим по Минску и до {$cfg['maxOutsideMkadKm']} км за МКАД. Этот район дальше: позвоните нам, что-нибудь придумаем", 'field' => 'address'];
        }

        if ($o['method'] === 'PICKUP') {
            $shopCode = $cfg['shops'][0]['code'];
            foreach ($cfg['shops'] as $s) if ($s['code'] === $o['shop']) $shopCode = $s['code'];
        } else {
            // Кнопка зоны — без точки: магазин по умолчанию первый, флорист поменяет при подтверждении
            $shopCode = !empty($t['quote']['shop']) ? $t['quote']['shop']['code'] : $cfg['shops'][0]['code'];
        }
        $q = $t['quote'];

        try {
            $id = Db::tx(function () use ($o, $t, $q, $unknown, $shopCode) {
                $id = Db::insert('orders', [
                    'customerName' => $o['customerName'],
                    'customerPhone' => $o['customerPhone'],
                    'recipientName' => $o['recipientName'],
                    'recipientPhone' => $o['recipientPhone'],
                    'callRecipient' => (int) $o['callRecipient'],
                    'anonymous' => (int) $o['anonymous'],
                    'cardText' => $o['cart']['cardText'],
                    'comment' => $o['comment'],
                    'deliveryMethod' => $o['method'],
                    'address' => $o['method'] === 'DELIVERY' && !$unknown ? Text::fullAddress($o) : '',
                    'addressUnknown' => (int) $unknown,
                    'shop' => $shopCode,
                    'deliveryZone' => ($q['ok'] ?? false) && !isset($q['area']) ? $q['zone'] : null,
                    'distanceKm' => ($q['ok'] ?? false) ? $q['km'] : null,
                    'deliveryDate' => $o['date'],
                    'deliverySlot' => $unknown ? '' : $o['slot'],
                    'paymentMethod' => $o['payment'],
                    'company' => $o['company'] ? json_encode($o['company'], JSON_UNESCAPED_UNICODE) : null,
                    'itemsKop' => $t['priced']['bouquetsKop'] + $t['priced']['addonsKop'],
                    'discountKop' => $t['discountKop'],
                    'deliveryKop' => $t['deliveryKop'],
                    'totalKop' => $t['totalKop'],
                    // Второй такой же запрос, пришедший одновременно, упрётся в уникальный ключ (см. catch ниже).
                    'idempotencyKey' => $o['idempotencyKey'],
                ]);
                Db::exec('UPDATE orders SET number = 10000 + id WHERE id = ?', [$id]);
                foreach ($t['priced']['lines'] as $l) {
                    Db::insert('order_items', ['orderId' => $id, 'bouquetId' => $l['bouquetId'], 'name' => $l['name'], 'size' => $l['size'],
                        'composition' => $l['composition'], 'qty' => $l['qty'], 'priceKop' => $l['priceKop']]);
                }
                foreach ($t['priced']['addons'] as $a) {
                    Db::insert('order_items', ['orderId' => $id, 'addonId' => $a['addonId'], 'name' => $a['name'], 'composition' => '',
                        'qty' => $a['qty'], 'priceKop' => $a['priceKop']]);
                }
                return $id;
            });
        } catch (Throwable $e) {
            if ($o['idempotencyKey'] && Db::isDuplicate($e) && ($existing = self::byKey($o['idempotencyKey']))) return $existing;
            throw $e;
        }
        $number = 10000 + $id;
        return ['ok' => true, 'number' => $number, 'token' => Auth::orderToken($number), 'id' => $id, 'new' => true];
    }

    private static function byKey(string $key): ?array
    {
        $r = Db::one('SELECT id, number FROM orders WHERE idempotencyKey = ?', [$key]);
        if (!$r) return null;
        return ['ok' => true, 'number' => (int) $r['number'], 'token' => Auth::orderToken((int) $r['number']), 'id' => (int) $r['id'], 'new' => false];
    }

    // Заказ с позициями для страницы «Заказ принят», письма и Telegram. Нет — null.
    public static function order(string $where, array $params): ?array
    {
        $o = Db::one("SELECT * FROM orders WHERE $where", $params);
        if (!$o) return null;
        foreach (['id', 'number', 'itemsKop', 'discountKop', 'deliveryKop', 'totalKop', 'notifyAttempts', 'emailAttempts'] as $k) $o[$k] = (int) $o[$k];
        foreach (['callRecipient', 'anonymous', 'addressUnknown', 'paid'] as $k) $o[$k] = (bool) $o[$k];
        $o['company'] = isset($o['company']) ? Db::fromJson($o['company'], null) : null;
        $o['deliveryZone'] = $o['deliveryZone'] === null ? null : (int) $o['deliveryZone'];
        $o['items'] = array_map(fn ($i) => [
            'id' => (int) $i['id'], 'bouquetId' => $i['bouquetId'] === null ? null : (int) $i['bouquetId'], 'name' => $i['name'],
            'size' => $i['size'], 'composition' => $i['composition'], 'qty' => (int) $i['qty'], 'priceKop' => (int) $i['priceKop'],
        ], Db::all('SELECT * FROM order_items WHERE orderId = ? ORDER BY id', [$o['id']]));
        return $o;
    }
}
