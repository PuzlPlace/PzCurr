<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Currency;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Currency\PzCurrCurrency;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Support\PzCurrRoundingHelper;

final class CurrencyRoundingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        PzCurrCurrencyRegistry::resetCustom();
    }

    protected function tearDown(): void
    {
        PzCurrCurrencyRegistry::resetCustom();
    }

    // -----------------------------------------------------------------------
    // Moeda customizada + arredondamento
    // -----------------------------------------------------------------------

    public function test_btc_registered_and_rounded_half_up(): void
    {
        PzCurrCurrencyRegistry::register(new PzCurrCurrency('BTC', 0, 8, '₿'));

        $currency = PzCurrCurrencyRegistry::of('BTC');
        $this->assertSame(8, $currency->scale);

        $result = PzCurrRoundingHelper::round('1.234567895', $currency->scale, PzCurrRoundingModeEnum::HALF_UP);

        // 1.234567895 → guide digit at position 8 is '5', round up → 1.23456790
        $this->assertSame('1.23456790', $result);
    }

    public function test_btc_registered_and_rounded_half_down(): void
    {
        PzCurrCurrencyRegistry::register(new PzCurrCurrency('BTC', 0, 8, '₿'));

        $currency = PzCurrCurrencyRegistry::of('BTC');

        $result = PzCurrRoundingHelper::round('1.234567895', $currency->scale, PzCurrRoundingModeEnum::HALF_DOWN);

        // dígito guia na posição 8 é '5', HALF_DOWN empata em direção a zero → trunca
        $this->assertSame('1.23456789', $result);
    }

    public function test_custom_currency_with_zero_scale_and_rounding(): void
    {
        PzCurrCurrencyRegistry::register(new PzCurrCurrency('VCN', 0, 0, 'V'));

        $currency = PzCurrCurrencyRegistry::of('VCN');
        $this->assertSame(0, $currency->scale);

        $result = PzCurrRoundingHelper::round('99.5', $currency->scale, PzCurrRoundingModeEnum::HALF_UP);
        $this->assertSame('100', $result);
    }

    public function test_iso_currency_brl_with_half_even_rounding(): void
    {
        $currency = PzCurrCurrencyRegistry::of('BRL');
        $this->assertSame(2, $currency->scale);

        // 2.345 → last kept digit '4' (even) → truncate
        $result = PzCurrRoundingHelper::round('2.345', $currency->scale, PzCurrRoundingModeEnum::HALF_EVEN);
        $this->assertSame('2.34', $result);

        // 2.355 → last kept digit '5' (odd) → round up
        $result2 = PzCurrRoundingHelper::round('2.355', $currency->scale, PzCurrRoundingModeEnum::HALF_EVEN);
        $this->assertSame('2.36', $result2);
    }

    public function test_iso_currency_jpy_with_floor_rounding(): void
    {
        $currency = PzCurrCurrencyRegistry::of('JPY');
        $this->assertSame(0, $currency->scale);

        // Negativo: FLOOR em direção a −∞
        $result = PzCurrRoundingHelper::round('-100.7', $currency->scale, PzCurrRoundingModeEnum::FLOOR);
        $this->assertSame('-101', $result);

        // Positive: FLOOR = truncate
        $result2 = PzCurrRoundingHelper::round('100.7', $currency->scale, PzCurrRoundingModeEnum::FLOOR);
        $this->assertSame('100', $result2);
    }

    public function test_custom_currency_not_found_before_registration(): void
    {
        $this->assertFalse(PzCurrCurrencyRegistry::has('XYZ'));

        PzCurrCurrencyRegistry::register(new PzCurrCurrency('XYZ', 0, 3, 'X'));

        $this->assertTrue(PzCurrCurrencyRegistry::has('XYZ'));

        $currency = PzCurrCurrencyRegistry::of('XYZ');
        $this->assertSame(3, $currency->scale);
    }

    public function test_all_rounding_modes_produce_different_results_for_midpoint(): void
    {
        // 2.345 at scale 2 — all modes should produce consistent and different results
        $amount = '2.345';
        $scale  = 2;

        $results = [
            'HALF_UP'   => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::HALF_UP),
            'HALF_DOWN' => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::HALF_DOWN),
            'HALF_EVEN' => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::HALF_EVEN),
            'UP'        => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::UP),
            'DOWN'      => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::DOWN),
            'CEILING'   => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::CEILING),
            'FLOOR'     => PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::FLOOR),
        ];

        $this->assertSame('2.35', $results['HALF_UP']);
        $this->assertSame('2.34', $results['HALF_DOWN']);
        $this->assertSame('2.34', $results['HALF_EVEN']);
        $this->assertSame('2.35', $results['UP']);
        $this->assertSame('2.34', $results['DOWN']);
        $this->assertSame('2.35', $results['CEILING']);
        $this->assertSame('2.34', $results['FLOOR']);
    }
}
