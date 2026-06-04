<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Currency;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Currency\PzCurrCurrency;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Exception\PzCurrInvalidCurrencyException;

final class PzCurrCurrencyRegistryTest extends TestCase
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
    // ISO resolution
    // -----------------------------------------------------------------------

    public function test_resolves_brl_with_correct_scale_and_symbol(): void
    {
        $currency = PzCurrCurrencyRegistry::of('BRL');

        $this->assertSame('BRL', $currency->code);
        $this->assertSame(986, $currency->numericCode);
        $this->assertSame(2, $currency->scale);
        $this->assertSame('R$', $currency->symbol);
    }

    public function test_resolves_usd_with_correct_scale(): void
    {
        $currency = PzCurrCurrencyRegistry::of('USD');

        $this->assertSame('USD', $currency->code);
        $this->assertSame(840, $currency->numericCode);
        $this->assertSame(2, $currency->scale);
    }

    public function test_resolves_eur_with_correct_scale(): void
    {
        $currency = PzCurrCurrencyRegistry::of('EUR');

        $this->assertSame('EUR', $currency->code);
        $this->assertSame(978, $currency->numericCode);
        $this->assertSame(2, $currency->scale);
    }

    public function test_resolves_jpy_with_zero_scale(): void
    {
        $currency = PzCurrCurrencyRegistry::of('JPY');

        $this->assertSame('JPY', $currency->code);
        $this->assertSame(392, $currency->numericCode);
        $this->assertSame(0, $currency->scale);
    }

    public function test_resolution_is_case_insensitive_lowercase(): void
    {
        $currency = PzCurrCurrencyRegistry::of('brl');

        $this->assertSame('BRL', $currency->code);
        $this->assertSame(2, $currency->scale);
    }

    public function test_resolution_is_case_insensitive_mixed(): void
    {
        $currency = PzCurrCurrencyRegistry::of('uSd');

        $this->assertSame('USD', $currency->code);
    }

    public function test_of_returns_pzcurr_currency_instance(): void
    {
        $this->assertInstanceOf(PzCurrCurrency::class, PzCurrCurrencyRegistry::of('BRL'));
    }

    // -----------------------------------------------------------------------
    // Invalid currency
    // -----------------------------------------------------------------------

    public function test_throws_for_unknown_currency_code(): void
    {
        $this->expectException(PzCurrInvalidCurrencyException::class);
        PzCurrCurrencyRegistry::of('XXX');
    }

    public function test_exception_message_contains_the_invalid_code(): void
    {
        try {
            PzCurrCurrencyRegistry::of('INVALID');
            $this->fail('Expected exception not thrown');
        } catch (PzCurrInvalidCurrencyException $e) {
            $this->assertStringContainsString('INVALID', $e->getMessage());
        }
    }

    public function test_throws_for_empty_string(): void
    {
        $this->expectException(PzCurrInvalidCurrencyException::class);
        PzCurrCurrencyRegistry::of('');
    }

    // -----------------------------------------------------------------------
    // has()
    // -----------------------------------------------------------------------

    public function test_has_returns_true_for_iso_currency(): void
    {
        $this->assertTrue(PzCurrCurrencyRegistry::has('BRL'));
        $this->assertTrue(PzCurrCurrencyRegistry::has('USD'));
        $this->assertTrue(PzCurrCurrencyRegistry::has('JPY'));
    }

    public function test_has_returns_false_for_unknown_currency(): void
    {
        $this->assertFalse(PzCurrCurrencyRegistry::has('XXX'));
    }

    public function test_has_is_case_insensitive(): void
    {
        $this->assertTrue(PzCurrCurrencyRegistry::has('brl'));
        $this->assertTrue(PzCurrCurrencyRegistry::has('eur'));
    }

    // -----------------------------------------------------------------------
    // Custom currency registration
    // -----------------------------------------------------------------------

    public function test_registers_and_resolves_custom_currency(): void
    {
        $btc = new PzCurrCurrency('BTC', 0, 8, '₿');
        PzCurrCurrencyRegistry::register($btc);

        $resolved = PzCurrCurrencyRegistry::of('BTC');

        $this->assertSame('BTC', $resolved->code);
        $this->assertSame(8, $resolved->scale);
        $this->assertSame('₿', $resolved->symbol);
    }

    public function test_custom_currency_is_found_by_has(): void
    {
        $eth = new PzCurrCurrency('ETH', 0, 18, 'Ξ');
        PzCurrCurrencyRegistry::register($eth);

        $this->assertTrue(PzCurrCurrencyRegistry::has('ETH'));
    }

    public function test_custom_currency_takes_precedence_over_iso(): void
    {
        // Override EUR with a custom entry to verify custom takes precedence.
        $custom = new PzCurrCurrency('EUR', 978, 4, '€€');
        PzCurrCurrencyRegistry::register($custom);

        $resolved = PzCurrCurrencyRegistry::of('EUR');

        $this->assertSame(4, $resolved->scale);
        $this->assertSame('€€', $resolved->symbol);
    }

    public function test_register_normalizes_code_to_uppercase(): void
    {
        $btc = new PzCurrCurrency('btc', 0, 8, '₿');
        PzCurrCurrencyRegistry::register($btc);

        $this->assertTrue(PzCurrCurrencyRegistry::has('BTC'));
        $this->assertTrue(PzCurrCurrencyRegistry::has('btc'));
    }

    public function test_register_lowercase_code_resolves_to_uppercase_value_object(): void
    {
        PzCurrCurrencyRegistry::register(new PzCurrCurrency('btc', 0, 8, '₿'));

        // Regardless of the casing used at registration, the resolved value object
        // must expose the canonical uppercase code so comparison and serialization
        // stay consistent across instances.
        $this->assertSame('BTC', PzCurrCurrencyRegistry::of('btc')->code);
        $this->assertSame('BTC', PzCurrCurrencyRegistry::of('BTC')->code);
    }

    public function test_reset_custom_removes_registered_currencies(): void
    {
        PzCurrCurrencyRegistry::register(new PzCurrCurrency('ZZZ', 0, 2, 'Z'));
        PzCurrCurrencyRegistry::resetCustom();

        $this->assertFalse(PzCurrCurrencyRegistry::has('ZZZ'));
    }

    // -----------------------------------------------------------------------
    // Value Object immutability
    // -----------------------------------------------------------------------

    public function test_currency_properties_are_readonly(): void
    {
        $currency = PzCurrCurrencyRegistry::of('BRL');

        $this->assertSame('BRL', $currency->code);
        $this->assertSame(2, $currency->scale);
    }

    public function test_currency_is_pzcurr_currency_instance(): void
    {
        $this->assertInstanceOf(PzCurrCurrency::class, PzCurrCurrencyRegistry::of('USD'));
    }
}
