<?php
// Распознавание фото для «Закупки и списания»: накладная, приход из тетради, лист списания.
// Фото уходит в Claude (Anthropic API), ответ — строго по схеме JSON. На сайте результат только заполняет
// черновик: администратор проверяет и сам жмёт «Сохранить». Фото нигде не сохраняются.
// Нужен ключ anthropic_api_key в app/config.php (без него кнопки «Фото» нет, всё вносится вручную).
// Composer на хостинге нет, поэтому запрос — обычный curl к https://api.anthropic.com/v1/messages.
final class Recognize
{
    private const URL = 'https://api.anthropic.com/v1/messages';
    public const MAX_PHOTOS = 6;
    public const MAX_PHOTO_BYTES = 4_500_000; // браузер уменьшает фото до 2000 px, это ~0,5–1,5 МБ

    // Для проверок: function (array $body): array{int, string} вместо настоящего запроса
    public static $transport = null;

    public static function ready(): bool
    {
        return (string) Config::get('anthropic_api_key') !== '';
    }

    // $photos: [[mime, байты], …]; $items: названия позиций; $shops: [[code, name], …]
    public static function invoice(array $photos, array $items): array
    {
        $prompt = "На фото — накладная поставщика цветочного магазина (ТТН/ТН, может быть несколько страниц, снятых телефоном). "
            . "Перепиши товарный раздел построчно.\n"
            . "- name: наименование товара как в накладной (с артикулом, размером, цветом), без «страна происхождения».\n"
            . "- qty: количество.\n"
            . "- sumKop: стоимость строки С НДС в копейках (графа «Стоимость с НДС»; если НДС нет — «Стоимость»). 220,08 руб. → 22008.\n"
            . "- item: название из списка позиций магазина ниже, если строка точно ему соответствует (тот же цветок/товар), иначе пустая строка.\n"
            . "supplier — грузоотправитель (короткое название, например «АП-Салют групп»), docNumber — номер накладной, "
            . "docDate — дата в виде ГГГГ-ММ-ДД или пустая строка, totalKop — «Всего стоимость с НДС» (итог) в копейках или 0, если не видно.\n"
            . "Если страницы повторяются (одна и та же строка на двух фото), не дублируй строку. Ничего не придумывай: неразборчивое число лучше оставить как видишь.\n\n"
            . "Позиции магазина:\n" . self::list($items);
        $line = self::obj(['name' => ['type' => 'string'], 'qty' => ['type' => 'number'], 'sumKop' => ['type' => 'integer'], 'item' => ['type' => 'string']]);
        $schema = self::obj([
            'supplier' => ['type' => 'string'], 'docNumber' => ['type' => 'string'], 'docDate' => ['type' => 'string'],
            'totalKop' => ['type' => 'integer'], 'lines' => ['type' => 'array', 'items' => $line],
        ]);
        return self::ask($photos, $prompt, $schema);
    }

    // Приход, расписанный в тетради по магазинам: «Коробка бархат S | всего 12 | Чечота 6 | Ур 6»
    public static function split(array $photos, array $items, array $shops): array
    {
        $prompt = "На фото — запись в тетради цветочного магазина: какой товар сколько пришло и сколько ушло в каждый магазин. "
            . "Перепиши каждую строку.\n"
            . "- name: название товара как написано.\n- item: название из списка позиций ниже, если оно точно соответствует, иначе пустая строка.\n"
            . "- total: «всего», если есть; иначе сумма по магазинам.\n- shops: сколько в каждый магазин (0, если не указано).\n"
            . self::shopHint($shops) . "\nПозиции магазина:\n" . self::list($items);
        $line = self::obj(['name' => ['type' => 'string'], 'item' => ['type' => 'string'], 'total' => ['type' => 'number'], 'shops' => self::obj(array_fill_keys(array_column($shops, 0), ['type' => 'number']))]);
        return self::ask($photos, $prompt, self::obj(['lines' => ['type' => 'array', 'items' => $line]]));
    }

    // Лист списания из тетради
    public static function writeoff(array $photos, array $items, array $shops): array
    {
        $reasons = [];
        foreach (Stock::REASONS as $code => $label) $reasons[] = "$code — $label";
        $prompt = "На фото — запись о списании в цветочном магазине (что выбросили и сколько). Перепиши каждую строку.\n"
            . "- name: что списали, как написано.\n- item: название из списка позиций ниже, если оно точно соответствует, иначе пустая строка.\n"
            . "- qty: сколько штук/стеблей.\n- shop: код магазина, если он указан в строке или в заголовке, иначе пустая строка.\n"
            . "- reason: причина, если указана: " . implode(', ', $reasons) . "; иначе пустая строка.\n"
            . "date — дата списания в виде ГГГГ-ММ-ДД, если написана, иначе пустая строка.\n"
            . self::shopHint($shops) . "\nПозиции магазина:\n" . self::list($items);
        $line = self::obj([
            'name' => ['type' => 'string'], 'item' => ['type' => 'string'], 'qty' => ['type' => 'number'],
            'shop' => ['type' => 'string', 'enum' => [...array_column($shops, 0), '']],
            'reason' => ['type' => 'string', 'enum' => [...array_keys(Stock::REASONS), '']],
        ]);
        return self::ask($photos, $prompt, self::obj(['date' => ['type' => 'string'], 'lines' => ['type' => 'array', 'items' => $line]]));
    }

    private static function shopHint(array $shops): string
    {
        $out = "Магазины (коды): ";
        foreach ($shops as [$code, $name, $extra]) $out .= "$code — $name" . ($extra ? " ($extra)" : '') . '; ';
        return rtrim($out, '; ') . '.';
    }

    private static function list(array $items): string
    {
        return $items ? '- ' . implode("\n- ", $items) : '(пока пусто)';
    }

    // Объект JSON-схемы: все поля обязательны, лишних нет (так требует строгий формат ответа)
    private static function obj(array $props): array
    {
        return ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false];
    }

    private static function ask(array $photos, string $prompt, array $schema): array
    {
        if (!self::ready()) return ['ok' => false, 'error' => 'Распознавание фото не подключено: нужен ключ anthropic_api_key в app/config.php.'];
        $content = [];
        foreach ($photos as [$mime, $bytes]) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];
        $body = [
            'model' => (string) (Config::get('anthropic_model') ?: 'claude-opus-5-5'),
            'max_tokens' => 16000,
            // если модель откажется отвечать (бывает на ложных срабатываниях фильтров), API само попробует другую
            'fallbacks' => 'default',
            'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            'messages' => [['role' => 'user', 'content' => $content]],
        ];
        try {
            [$code, $answer] = self::$transport ? (self::$transport)($body) : self::send($body);
        } catch (Throwable $e) {
            app_log('Recognize: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Нет связи с сервисом распознавания. Попробуйте ещё раз или внесите вручную.'];
        }
        return self::parse($code, $answer);
    }

    // Ответ API → ['ok' => true, 'data' => …] или понятная ошибка
    public static function parse(int $code, string $answer): array
    {
        $r = json_decode($answer, true);
        if ($code !== 200 || !is_array($r)) {
            $msg = is_array($r) ? (string) ($r['error']['message'] ?? '') : '';
            if (function_exists('app_log')) app_log("Recognize: HTTP $code $msg");
            if ($code === 401 || $code === 403) return ['ok' => false, 'error' => 'Ключ распознавания не подходит. Проверьте anthropic_api_key в app/config.php.'];
            if ($code === 429 || $code === 529 || $code >= 500) return ['ok' => false, 'error' => 'Сервис распознавания сейчас занят. Попробуйте через минуту.'];
            if ($code === 413 || str_contains($msg, 'image')) return ['ok' => false, 'error' => 'Фото не подошло (слишком большое или повреждено). Снимите ещё раз.'];
            return ['ok' => false, 'error' => 'Не удалось распознать фото. Попробуйте ещё раз или внесите вручную.'];
        }
        $stop = $r['stop_reason'] ?? '';
        if ($stop === 'refusal') return ['ok' => false, 'error' => 'Сервис отказался разбирать это фото. Внесите строки вручную.'];
        if ($stop === 'max_tokens') return ['ok' => false, 'error' => 'На фото слишком много строк за раз. Загрузите страницы по одной.'];
        $text = '';
        foreach ($r['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') $text .= $block['text'];
        }
        $data = json_decode($text, true);
        if (!is_array($data)) return ['ok' => false, 'error' => 'Не удалось разобрать ответ. Попробуйте ещё раз.'];
        return ['ok' => true, 'data' => $data];
    }

    private static function send(array $body): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('нет curl');
        @set_time_limit(150);
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 140,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . Config::get('anthropic_api_key'),
                'anthropic-version: 2026-10-21',
                'anthropic-beta: server-side-fallback-2026-07-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $answer = curl_exec($ch);
        if ($answer === false) throw new RuntimeException(curl_error($ch));
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$code, (string) $answer];
    }
}
