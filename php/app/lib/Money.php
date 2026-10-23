<?php
// Деньги храним в копейках. 17250 → «172,50 BYN», 13000 → «130 BYN».
final class Money
{
    public static function format(int|float $kop): string
    {
        $sign = $kop < 0 ? '−' : '';
        $abs = (int) abs(round($kop));
        $rub = intdiv($abs, 100);
        $k = $abs % 100;
        $rubStr = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', (string) $rub);
        return $sign . $rubStr . ($k ? ',' . str_pad((string) $k, 2, '0', STR_PAD_LEFT) : '') . ' BYN';
    }

    // «10,5» или «10.50» → 1050. Пустая строка или мусор → null.
    public static function parse(string $input): ?int
    {
        $s = str_replace(',', '.', preg_replace('/\s/u', '', trim($input)));
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) return null;
        return (int) round((float) $s * 100);
    }

    // Для полей ввода в админке: 1050 → «10,50»
    public static function toInput(int $kop): string
    {
        return str_replace('.', ',', number_format($kop / 100, 2, '.', ''));
    }

    public static function percentOff(int $kop, int|float $pct): int
    {
        return (int) round($kop * $pct / 100);
    }
}
