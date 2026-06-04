<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Exception\PzCurrFloatNotAllowedException;

/**
 * Cobre a aceitação de float nos métodos quando o modo STRICT está desativado
 * (padrão) e a rejeição via exceção quando STRICT está ativado.
 *
 * Estratégia de conversão escolhida: number_format pela escala da instância
 * (determinística, sem notação científica, locale-safe).
 */
final class PzCurrBcMathFloatInputTest extends TestCase
{
    private function lenient(): PzCurrBcMath
    {
        return new PzCurrBcMath(null, false);
    }

    private function strict(): PzCurrBcMath
    {
        return new PzCurrBcMath(null, true);
    }

    // -------------------------------------------------------------------------
    // STRICT desativado (padrão) — aceita float
    // -------------------------------------------------------------------------

    public function test_default_constructor_is_lenient_and_accepts_float_in_of(): void
    {
        $m = (new PzCurrBcMath())->of(19.90, PzCurrCurrencyEnum::BRL);

        $this->assertSame('19.90', $m->getAmount());
    }

    public function test_float_in_of_is_rounded_to_currency_scale(): void
    {
        // 1.239 convertido pela escala 2 (BRL) → '1.24' (number_format HALF_UP)
        $m = $this->lenient()->of(1.239, PzCurrCurrencyEnum::BRL);

        $this->assertSame('1.24', $m->getAmount());
    }

    public function test_float_in_of_respects_explicit_scale(): void
    {
        $m = $this->lenient()->of(1.23456789, PzCurrCurrencyEnum::BRL, 4);

        $this->assertSame('1.2346', $m->getAmount());
    }

    public function test_float_in_add(): void
    {
        $m = $this->lenient()->of('10.00', PzCurrCurrencyEnum::BRL)->add(4.99);

        $this->assertSame('14.99', $m->getAmount());
    }

    public function test_float_in_subtract(): void
    {
        $m = $this->lenient()->of('10.00', PzCurrCurrencyEnum::BRL)->subtract(0.50);

        $this->assertSame('9.50', $m->getAmount());
    }

    public function test_float_in_multiply(): void
    {
        $m = $this->lenient()->of('10.00', PzCurrCurrencyEnum::BRL)->multiply(2.5);

        $this->assertSame('25.00', $m->getAmount());
    }

    public function test_float_in_divide(): void
    {
        $m = $this->lenient()->of('10.00', PzCurrCurrencyEnum::BRL)->divide(2.0);

        $this->assertSame('5.00', $m->getAmount());
    }

    public function test_classic_float_precision_issue_is_handled(): void
    {
        // 0.1 + 0.2 com floats nativos = 0.30000000000000004; aqui some o drift.
        $m = $this->lenient()->of(0.1, PzCurrCurrencyEnum::BRL)->add(0.2);

        $this->assertSame('0.30', $m->getAmount());
    }

    public function test_float_in_comparisons(): void
    {
        $m = $this->lenient()->of('10.00', PzCurrCurrencyEnum::BRL);

        $this->assertTrue($m->isEqualTo(10.00));
        $this->assertTrue($m->isGreaterThan(9.99));
        $this->assertSame(0, $m->compareTo(10.0));
    }

    public function test_mixed_string_int_float_operands(): void
    {
        $m = $this->lenient()
            ->of('25.00', PzCurrCurrencyEnum::BRL)
            ->add('4.99', 5, 1.01);

        $this->assertSame('36.00', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // STRICT ativado — rejeita float
    // -------------------------------------------------------------------------

    public function test_strict_rejects_float_in_of(): void
    {
        $this->expectException(PzCurrFloatNotAllowedException::class);
        $this->strict()->of(19.90, PzCurrCurrencyEnum::BRL);
    }

    public function test_strict_rejects_float_in_add(): void
    {
        $this->expectException(PzCurrFloatNotAllowedException::class);
        $this->strict()->of('10.00', PzCurrCurrencyEnum::BRL)->add(1.5);
    }

    public function test_strict_rejects_float_in_multiply(): void
    {
        $this->expectException(PzCurrFloatNotAllowedException::class);
        $this->strict()->of('10.00', PzCurrCurrencyEnum::BRL)->multiply(2.5);
    }

    public function test_strict_rejects_float_in_divide(): void
    {
        $this->expectException(PzCurrFloatNotAllowedException::class);
        $this->strict()->of('10.00', PzCurrCurrencyEnum::BRL)->divide(2.0);
    }

    public function test_strict_rejects_float_in_comparison(): void
    {
        $this->expectException(PzCurrFloatNotAllowedException::class);
        $this->strict()->of('10.00', PzCurrCurrencyEnum::BRL)->isEqualTo(10.0);
    }

    // -------------------------------------------------------------------------
    // STRICT ativado — string e int continuam funcionando (não-regressão)
    // -------------------------------------------------------------------------

    public function test_strict_still_accepts_string_and_int(): void
    {
        $m = $this->strict()
            ->of('25.00', PzCurrCurrencyEnum::BRL)
            ->add('4.99', 5)
            ->multiply('2');

        $this->assertSame('69.98', $m->getAmount());
    }
}
