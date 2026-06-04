<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Currency;

use Puzl\PzCurr\Exception\PzCurrInvalidCurrencyException;

final class PzCurrCurrencyRegistry
{
    /** @var array<string, PzCurrCurrency> */
    private static array $custom = [];

    /**
     * Catálogo ISO 4217 de moedas.
     *
     * @var array<string, array{numeric: int, scale: int, symbol: string}>
     */
    private const ISO = [
        'AED' => ['numeric' => 784,  'scale' => 2, 'symbol' => 'د.إ'],
        'ARS' => ['numeric' => 32,   'scale' => 2, 'symbol' => '$'],
        'AUD' => ['numeric' => 36,   'scale' => 2, 'symbol' => 'A$'],
        'BHD' => ['numeric' => 48,   'scale' => 3, 'symbol' => 'BD'],
        'BRL' => ['numeric' => 986,  'scale' => 2, 'symbol' => 'R$'],
        'CAD' => ['numeric' => 124,  'scale' => 2, 'symbol' => 'CA$'],
        'CHF' => ['numeric' => 756,  'scale' => 2, 'symbol' => 'Fr'],
        'CLP' => ['numeric' => 152,  'scale' => 0, 'symbol' => '$'],
        'CNY' => ['numeric' => 156,  'scale' => 2, 'symbol' => '¥'],
        'COP' => ['numeric' => 170,  'scale' => 2, 'symbol' => '$'],
        'CZK' => ['numeric' => 203,  'scale' => 2, 'symbol' => 'Kč'],
        'DKK' => ['numeric' => 208,  'scale' => 2, 'symbol' => 'kr'],
        'EGP' => ['numeric' => 818,  'scale' => 2, 'symbol' => '£'],
        'EUR' => ['numeric' => 978,  'scale' => 2, 'symbol' => '€'],
        'GBP' => ['numeric' => 826,  'scale' => 2, 'symbol' => '£'],
        'HKD' => ['numeric' => 344,  'scale' => 2, 'symbol' => 'HK$'],
        'HUF' => ['numeric' => 348,  'scale' => 2, 'symbol' => 'Ft'],
        'IDR' => ['numeric' => 360,  'scale' => 2, 'symbol' => 'Rp'],
        'ILS' => ['numeric' => 376,  'scale' => 2, 'symbol' => '₪'],
        'INR' => ['numeric' => 356,  'scale' => 2, 'symbol' => '₹'],
        'IQD' => ['numeric' => 368,  'scale' => 3, 'symbol' => 'ع.د'],
        'IRR' => ['numeric' => 364,  'scale' => 2, 'symbol' => '﷼'],
        'ISK' => ['numeric' => 352,  'scale' => 0, 'symbol' => 'kr'],
        'JOD' => ['numeric' => 400,  'scale' => 3, 'symbol' => 'JD'],
        'JPY' => ['numeric' => 392,  'scale' => 0, 'symbol' => '¥'],
        'KRW' => ['numeric' => 410,  'scale' => 0, 'symbol' => '₩'],
        'KWD' => ['numeric' => 414,  'scale' => 3, 'symbol' => 'KD'],
        'MXN' => ['numeric' => 484,  'scale' => 2, 'symbol' => '$'],
        'MYR' => ['numeric' => 458,  'scale' => 2, 'symbol' => 'RM'],
        'NOK' => ['numeric' => 578,  'scale' => 2, 'symbol' => 'kr'],
        'NZD' => ['numeric' => 554,  'scale' => 2, 'symbol' => 'NZ$'],
        'OMR' => ['numeric' => 512,  'scale' => 3, 'symbol' => 'ر.ع.'],
        'PEN' => ['numeric' => 604,  'scale' => 2, 'symbol' => 'S/'],
        'PHP' => ['numeric' => 608,  'scale' => 2, 'symbol' => '₱'],
        'PKR' => ['numeric' => 586,  'scale' => 2, 'symbol' => '₨'],
        'PLN' => ['numeric' => 985,  'scale' => 2, 'symbol' => 'zł'],
        'QAR' => ['numeric' => 634,  'scale' => 2, 'symbol' => 'ر.ق'],
        'RON' => ['numeric' => 946,  'scale' => 2, 'symbol' => 'lei'],
        'RUB' => ['numeric' => 643,  'scale' => 2, 'symbol' => '₽'],
        'SAR' => ['numeric' => 682,  'scale' => 2, 'symbol' => '﷼'],
        'SEK' => ['numeric' => 752,  'scale' => 2, 'symbol' => 'kr'],
        'SGD' => ['numeric' => 702,  'scale' => 2, 'symbol' => 'S$'],
        'THB' => ['numeric' => 764,  'scale' => 2, 'symbol' => '฿'],
        'TRY' => ['numeric' => 949,  'scale' => 2, 'symbol' => '₺'],
        'TWD' => ['numeric' => 901,  'scale' => 2, 'symbol' => 'NT$'],
        'UAH' => ['numeric' => 980,  'scale' => 2, 'symbol' => '₴'],
        'USD' => ['numeric' => 840,  'scale' => 2, 'symbol' => '$'],
        'UYU' => ['numeric' => 858,  'scale' => 2, 'symbol' => '$U'],
        'VND' => ['numeric' => 704,  'scale' => 0, 'symbol' => '₫'],
        'ZAR' => ['numeric' => 710,  'scale' => 2, 'symbol' => 'R'],
    ];

    /** Resolve código ISO para PzCurrCurrency (custom ou catálogo). */
    public static function of(string $code): PzCurrCurrency
    {
        $normalized = strtoupper($code);

        if (isset(self::$custom[$normalized])) {
            return self::$custom[$normalized];
        }

        if (isset(self::ISO[$normalized])) {
            $data = self::ISO[$normalized];
            return new PzCurrCurrency($normalized, $data['numeric'], $data['scale'], $data['symbol']);
        }

        throw PzCurrInvalidCurrencyException::forCode($code);
    }

    /** Registra moeda customizada (sobrescreve ISO se mesmo código). */
    public static function register(PzCurrCurrency $currency): void
    {
        $normalized = strtoupper($currency->code);

        // Armazena sob código normalizado em maiúsculas para comparação e serialização consistentes.
        self::$custom[$normalized] = $normalized === $currency->code
            ? $currency
            : new PzCurrCurrency(
                $normalized,
                $currency->numericCode,
                $currency->scale,
                $currency->symbol,
            );
    }

    /** Verifica se o código existe no catálogo ou em moedas customizadas. */
    public static function has(string $code): bool
    {
        $normalized = strtoupper($code);
        return isset(self::$custom[$normalized]) || isset(self::ISO[$normalized]);
    }

    /** Remove moedas customizadas registradas (útil para isolamento em testes). */
    public static function resetCustom(): void
    {
        self::$custom = [];
    }
}
