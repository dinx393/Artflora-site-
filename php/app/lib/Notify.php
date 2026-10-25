<?php
// Уведомления о новом заказе (повторяет src/lib/notify.ts): в Telegram (если в app/config.php заданы
// telegram_bot_token и telegram_chat_id) и письмом на почту магазина (smtp_* в config.php + адрес
// в админке → «Настройки»). Каналы независимы. Результат записываем в заказ: не дошло — повторит
// app/cron.php (раз в несколько минут), а админка покажет предупреждение.
final class Notify
{
    public const MAX_ATTEMPTS = 10;

    public static function telegramConfigured(): bool
    {
        return Config::get('telegram_bot_token') !== '' && Config::get('telegram_chat_id') !== '';
    }

    public static function smtpConfigured(): bool
    {
        return Config::get('smtp_host') !== '' && Config::get('smtp_user') !== '' && Config::get('smtp_pass') !== '';
    }

    // Куда слать письма о заказах: поле в админке → «Настройки» (можно несколько адресов через запятую).
    public static function orderEmailTo(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) (Settings::get('shop')['orderEmail'] ?? '')))));
    }

    public static function emailConfigured(): bool
    {
        return self::smtpConfigured() && (bool) self::orderEmailTo();
    }

    public static function newOrder(int $orderId): void
    {
        $o = Db::one('SELECT notifiedAt, emailedAt FROM orders WHERE id = ?', [$orderId]);
        if (!$o) return;
        if ($o['notifiedAt'] === null && self::telegramConfigured()) self::attempt($orderId, 'notify', fn () => self::sendTelegram($orderId));
        if ($o['emailedAt'] === null && self::emailConfigured()) self::attempt($orderId, 'email', fn () => self::sendEmail($orderId));
    }

    // $kind: notify (Telegram) или email — поля notifiedAt/notifyAttempts/notifyError или emailedAt/…
    private static function attempt(int $orderId, string $kind, callable $send): void
    {
        $at = $kind === 'notify' ? 'notifiedAt' : 'emailedAt';
        try {
            $send();
            Db::exec("UPDATE orders SET $at = NOW(), {$kind}Error = '' WHERE id = ?", [$orderId]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            app_log(($kind === 'notify' ? 'Уведомление в Telegram' : 'Письмо') . " о заказе id=$orderId не отправлено: $msg");
            try {
                Db::exec("UPDATE orders SET {$kind}Attempts = {$kind}Attempts + 1, {$kind}Error = ? WHERE id = ?", [mb_substr($msg, 0, 300), $orderId]);
            } catch (Throwable) {
            }
        }
    }

    // Повтор уведомлений, которые не дошли (за последние 2 дня, до 10 попыток). Вызывает app/cron.php.
    // → сколько заказов проверили
    public static function retryPending(): int
    {
        $or = [];
        if (self::telegramConfigured()) $or[] = '(notifiedAt IS NULL AND notifyAttempts < ' . self::MAX_ATTEMPTS . ')';
        if (self::smtpConfigured()) $or[] = '(emailedAt IS NULL AND emailAttempts < ' . self::MAX_ATTEMPTS . ')';
        if (!$or) return 0;
        $ids = Db::all('SELECT id FROM orders
            WHERE createdAt > NOW() - INTERVAL 2 DAY AND createdAt < NOW() - INTERVAL 30 SECOND AND (' . implode(' OR ', $or) . ')
            ORDER BY createdAt LIMIT 20');
        foreach ($ids as $r) self::newOrder((int) $r['id']);
        return count($ids);
    }

    public static function telegramText(array $o): string
    {
        $lines = [
            "🌸 Новый заказ №{$o['number']} — " . Money::format($o['totalKop']),
            ...array_map(fn ($i) => "• {$i['name']}" . ($i['size'] ? ", {$i['size']}" : '') . " × {$i['qty']}", $o['items']),
            $o['deliveryMethod'] === 'PICKUP'
                ? "Самовывоз: {$o['shop']}"
                : ($o['addressUnknown']
                    ? "Доставка: адрес и время уточнить у получателя {$o['recipientPhone']}, доплата за доставку отдельно"
                    : "Доставка: {$o['address']} (зона " . ($o['deliveryZone'] ?? '?') . ')'),
            "Когда: {$o['deliveryDate']}, " . ($o['deliverySlot'] ?: 'время уточнить'),
            "Клиент: {$o['customerName']}, {$o['customerPhone']}",
            ...(!empty($o['company'])
                ? ['💼 Безнал, реквизиты для счёта:', $o['company']['text'] ?? '']
                : []),
            Config::siteUrl() . "/admin/orders/{$o['id']}",
        ];
        return implode("\n", $lines);
    }

    private static function sendTelegram(int $orderId): void
    {
        $o = Checkout::order('id = ?', [$orderId]);
        if (!$o) return;
        $url = 'https://api.telegram.org/bot' . Config::get('telegram_bot_token') . '/sendMessage';
        $body = Db::json(['chat_id' => Config::get('telegram_chat_id'), 'text' => self::telegramText($o)]);
        [$status, $answer] = self::postJson($url, $body, 5);
        if ($status !== 200) throw new RuntimeException("Telegram ответил $status: " . mb_substr($answer, 0, 200));
    }

    // POST с JSON. → [код ответа, текст ответа]. Без расширения curl — через обычные потоки PHP.
    private static function postJson(string $url, string $body, int $timeout): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout,
            ]);
            $answer = curl_exec($ch);
            if ($answer === false) throw new RuntimeException('нет связи с Telegram: ' . curl_error($ch));
            return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string) $answer];
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $body,
            'timeout' => $timeout, 'ignore_errors' => true,
        ]]);
        $answer = @file_get_contents($url, false, $ctx);
        if ($answer === false) throw new RuntimeException('нет связи с Telegram');
        preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m);
        return [(int) ($m[1] ?? 0), $answer];
    }

    private static function mailer(): Smtp
    {
        return new Smtp((string) Config::get('smtp_host'), (int) (Config::get('smtp_port') ?: 465), (string) Config::get('smtp_user'), (string) Config::get('smtp_pass'));
    }

    private static function from(): string
    {
        return (string) (Config::get('smtp_from') ?: Config::get('smtp_user'));
    }

    // Полный адрес картинки для письма: почтовые программы не знают, где наш сайт.
    private static function absolute(?string $url): ?string
    {
        if (!$url) return null;
        if (preg_match('#^https?://#', $url)) return $url;
        $site = Config::siteUrl();
        return $site !== '' ? $site . (str_starts_with($url, '/') ? $url : "/$url") : null;
    }

    // Письмо о заказе: ['subject','html','text'] или null, если заказа нет.
    public static function buildOrderEmail(int $orderId): ?array
    {
        $o = Checkout::order('id = ?', [$orderId]);
        if (!$o) return null;
        $ids = array_values(array_filter(array_column($o['items'], 'bouquetId')));
        $photo = [];
        if ($ids) {
            foreach (Db::all('SELECT id, photos FROM bouquets WHERE id ' . Db::in($ids), $ids) as $b) $photo[(int) $b['id']] = Db::fromJson($b['photos'])[0] ?? null;
        }
        $shop = null;
        foreach (Settings::get('delivery')['shops'] as $s) if ($s['code'] === $o['shop']) $shop = $s;
        return OrderEmail::render([
            'number' => $o['number'],
            'createdAt' => strtotime($o['createdAt'] . ' +03:00'),
            'status' => OrderEmail::STATUS_LABEL[$o['status']] ?? $o['status'],
            'paid' => $o['paid'],
            'payment' => OrderEmail::PAY_LABEL[$o['paymentMethod']] ?? $o['paymentMethod'],
            'customerName' => $o['customerName'],
            'customerPhone' => $o['customerPhone'],
            'recipientName' => $o['recipientName'],
            'recipientPhone' => $o['recipientPhone'],
            'callRecipient' => $o['callRecipient'],
            'anonymous' => $o['anonymous'],
            'method' => $o['deliveryMethod'],
            'address' => $o['address'],
            'addressUnknown' => $o['addressUnknown'],
            'shopName' => $shop ? "{$shop['name']}, {$shop['address']}" : $o['shop'],
            'deliveryDate' => $o['deliveryDate'],
            'deliverySlot' => $o['deliverySlot'],
            'comment' => $o['comment'],
            'cardText' => $o['cardText'],
            'items' => array_map(fn ($i) => [
                'name' => $i['name'], 'size' => $i['size'], 'qty' => $i['qty'], 'priceKop' => $i['priceKop'],
                'photo' => self::absolute($i['bouquetId'] !== null ? ($photo[$i['bouquetId']] ?? null) : null),
            ], $o['items']),
            'itemsKop' => $o['itemsKop'],
            'discountKop' => $o['discountKop'],
            'deliveryKop' => $o['deliveryKop'],
            'totalKop' => $o['totalKop'],
            'adminUrl' => Config::siteUrl() . "/admin/orders/{$o['id']}",
            'company' => $o['company'],
        ]);
    }

    private static function sendEmail(int $orderId): void
    {
        $mail = self::buildOrderEmail($orderId);
        if (!$mail) return;
        self::mailer()->send(self::from(), 'Артфлора — заказы', self::orderEmailTo(), $mail['subject'], $mail['html'], $mail['text']);
    }

    // Пробное письмо из админки → «Настройки»: проверить, что почта настроена и письма доходят.
    // → ['ok' => true] | ['ok' => false, 'error' => текст для владельца]
    public static function sendTestEmail(string $to): array
    {
        if (!self::smtpConfigured()) {
            return ['ok' => false, 'error' => 'Почта-отправитель не настроена: в файле app/config.php не заполнены smtp_host, smtp_user или smtp_pass'];
        }
        $list = array_values(array_filter(array_map('trim', explode(',', $to))));
        if (!$list) return ['ok' => false, 'error' => 'Впишите выше, на какую почту присылать письма о заказах'];
        try {
            self::mailer()->send(self::from(), 'Артфлора — заказы', $list, 'Пробное письмо с сайта Артфлоры', '',
                'Это пробное письмо. Раз оно дошло, письма о новых заказах будут приходить сюда же.');
            return ['ok' => true];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            // Самая частая ошибка — обычный пароль вместо «пароля приложения».
            $hint = preg_match('/auth|535|534|login|credentials/i', $msg) ? ' Проверьте smtp_user и пароль приложения (не обычный пароль от почты).' : '';
            return ['ok' => false, 'error' => 'Письмо не ушло: ' . mb_substr($msg, 0, 200) . ".$hint"];
        }
    }

    // Ответить браузеру сразу, а уведомления отправить уже после: покупатель не ждёт почту и Telegram.
    // Если сервер так не умеет, уведомления уйдут до ответа (с короткими тайм-аутами), а не дошедшие повторит cron.
    public static function respondJsonThen(array $data, int $code, callable $after): never
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ignore_user_abort(true);
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) ob_end_flush();
            flush();
        }
        try {
            $after();
        } catch (Throwable $e) {
            app_log('Уведомление после заказа: ' . $e->getMessage());
        }
        exit;
    }
}
