<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Enum\PzCurrLocaleEnum;
use Puzl\PzCurr\Support\PzCurrFormatter;

final class PzCurrFormatterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Formatação manual pt-BR
    // -------------------------------------------------------------------------

    public function test_format_brl_manual_with_thousands(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('1234.56', $currency);

        $this->assertSame('R$ 1.234,56', $result);
    }

    public function test_format_brl_manual_below_thousand(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('99.90', $currency);

        $this->assertSame('R$ 99,90', $result);
    }

    public function test_format_brl_manual_large_value(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('1234567.89', $currency);

        $this->assertSame('R$ 1.234.567,89', $result);
    }

    public function test_format_brl_manual_zero(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('0.00', $currency);

        $this->assertSame('R$ 0,00', $result);
    }

    public function test_format_brl_manual_negative(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('-1234.56', $currency);

        $this->assertSame('R$ -1.234,56', $result);
    }

    // -------------------------------------------------------------------------
    // JPY — escala 0 (sem parte decimal)
    // -------------------------------------------------------------------------

    public function test_format_jpy_scale_zero(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('JPY');

        $result = $formatter->format('1234', $currency);

        $this->assertSame('¥ 1.234', $result);
    }

    public function test_format_jpy_small_value(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('JPY');

        $result = $formatter->format('100', $currency);

        $this->assertSame('¥ 100', $result);
    }

    // -------------------------------------------------------------------------
    // Degradação graciosa — intl ausente simulado
    // -------------------------------------------------------------------------

    public function test_format_falls_back_to_manual_when_intl_unavailable(): void
    {
        // intlAvailable=false simula ext-intl não carregada.
        $formatter = new PzCurrFormatter(intlAvailable: false);
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        // Mesmo com locale, deve produzir saída manual.
        $result = $formatter->format('1234.56', $currency, PzCurrLocaleEnum::PT_BR);

        $this->assertSame('R$ 1.234,56', $result);
    }

    public function test_format_without_locale_always_uses_manual(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('500.00', $currency, null);

        $this->assertSame('R$ 500,00', $result);
    }

    // -------------------------------------------------------------------------
    // Separadores configuráveis
    // -------------------------------------------------------------------------

    public function test_format_custom_separators(): void
    {
        // Estilo US: milhares=',', decimal='.'
        $formatter = new PzCurrFormatter(
            thousandsSeparator: ',',
            decimalSeparator: '.',
            symbolBefore: true,
        );
        $currency = PzCurrCurrencyRegistry::of('USD');

        $result = $formatter->format('1234.56', $currency);

        $this->assertSame('$ 1,234.56', $result);
    }

    public function test_format_symbol_after(): void
    {
        $formatter = new PzCurrFormatter(symbolBefore: false);
        $currency  = PzCurrCurrencyRegistry::of('EUR');

        $result = $formatter->format('100.00', $currency);

        $this->assertSame('100,00 €', $result);
    }

    // -------------------------------------------------------------------------
    // Casos limite — fronteiras do separador de milhares
    // -------------------------------------------------------------------------

    public function test_format_exactly_three_integer_digits(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('999.99', $currency);

        $this->assertSame('R$ 999,99', $result);
    }

    public function test_format_exactly_four_integer_digits(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('1000.00', $currency);

        $this->assertSame('R$ 1.000,00', $result);
    }

    public function test_format_seven_integer_digits(): void
    {
        $formatter = new PzCurrFormatter();
        $currency  = PzCurrCurrencyRegistry::of('BRL');

        $result = $formatter->format('1000000.00', $currency);

        $this->assertSame('R$ 1.000.000,00', $result);
    }
}
