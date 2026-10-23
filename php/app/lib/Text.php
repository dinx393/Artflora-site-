<?php
// Мелочи для текста: телефон, адрес, ссылки на мессенджеры.
final class Text
{
    // «+375291112233» → «+375 29 111-22-33». Другие форматы показываем как есть.
    public static function phone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (preg_match('/^375(\d{2})(\d{3})(\d{2})(\d{2})$/', $d, $m) || preg_match('/^80(\d{2})(\d{3})(\d{2})(\d{2})$/', $d, $m)) {
            return "+375 {$m[1]} {$m[2]}-{$m[3]}-{$m[4]}";
        }
        return $raw;
    }

    // Срок после «от»: «от 1 часа», «от 1,5 часа», «от 2 часов», «от 21 часа»
    public static function fromHours(int|float $h): string
    {
        $n = str_replace('.', ',', (string) round($h, 1));
        $one = fmod($h, 1) != 0 || ((int) $h % 10 === 1 && (int) $h % 100 !== 11);
        return "от $n " . ($one ? 'часа' : 'часов');
    }

    // «Уручье: ул. Немига, 5, кв. 12, подъезд 2, этаж 4» — так адрес видит флорист и курьер.
    // Район впереди: цену доставки считали по нему, при подтверждении заказа его сверяют с адресом.
    public static function fullAddress(array $o): string
    {
        $parts = array_filter([
            trim($o['address'] ?? ''),
            ($v = trim($o['apartment'] ?? '')) !== '' ? "кв. $v" : '',
            ($v = trim($o['entrance'] ?? '')) !== '' ? "подъезд $v" : '',
            ($v = trim($o['floor'] ?? '')) !== '' ? "этаж $v" : '',
        ], fn ($p) => $p !== '');
        $s = implode(', ', $parts);
        $district = trim($o['district'] ?? '');
        return $district !== '' ? "$district: $s" : $s;
    }

    // Куда ведёт номер в шапке: три мессенджера и звонок: [['code','label','href'], ...].
    // Если ссылка в настройках не заполнена, открываем мессенджер по номеру телефона.
    public static function contactLinks(array $shop): array
    {
        $digits = preg_replace('/\D/', '', $shop['phone']);
        $tg = trim($shop['telegram'] ?? '');
        $telegram = $tg === '' || preg_match('#^https://t\.me/?$#', $tg) ? "https://t.me/+$digits" : $tg;
        return [
            ['code' => 'telegram', 'label' => 'Telegram', 'href' => $telegram],
            ['code' => 'whatsapp', 'label' => 'WhatsApp', 'href' => trim($shop['whatsapp'] ?? '') ?: "https://wa.me/$digits"],
            ['code' => 'viber', 'label' => 'Viber', 'href' => trim($shop['viber'] ?? '') ?: "viber://chat?number=%2B$digits"],
            ['code' => 'call', 'label' => 'Позвонить', 'href' => "tel:+$digits"],
        ];
    }
}
