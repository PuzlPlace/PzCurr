<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;

/**
 * Cobre toFloat(): conversão correta para casos bem-comportados e documentação
 * (via teste) de que a fonte de verdade continua sendo a string getAmount().
 */
final class PzCurrBcMathToFloatTest extends TestCase
{
    public function test_to_float_returns_float_type(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', PzCurrCurrencyEnum::BRL);

        $this->assertIsFloat($m->toFloat());
    }

    public function test_to_float_simple_value(): void
    {
        $m = (new PzCurrBcMath())->of('25.00', PzCurrCurrencyEnum::BRL);

        $this->assertSame(25.0, $m->toFloat());
    }

    public function test_to_float_two_decimals(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', PzCurrCurrencyEnum::BRL);

        $this->assertSame(19.9, $m->toFloat());
    }

    public function test_to_float_negative_value(): void
    {
        $m = (new PzCurrBcMath())->of('-5.50', PzCurrCurrencyEnum::BRL);

        $this->assertSame(-5.5, $m->toFloat());
    }

    public function test_to_float_zero(): void
    {
        $m = (new PzCurrBcMath())->zero(PzCurrCurrencyEnum::BRL);

        $this->assertSame(0.0, $m->toFloat());
    }

    public function test_to_float_jpy_integer_scale(): void
    {
        $m = (new PzCurrBcMath())->of('1000', PzCurrCurrencyEnum::JPY);

        $this->assertSame(1000.0, $m->toFloat());
    }

    public function test_to_float_after_operations(): void
    {
        $m = (new PzCurrBcMath())
            ->of('100.00', PzCurrCurrencyEnum::BRL)
            ->subtract('10.00')
            ->multiply('2');

        $this->assertSame(180.0, $m->toFloat());
    }

    public function test_to_float_equals_manual_cast_of_get_amount(): void
    {
        $m = (new PzCurrBcMath())->of('1234.56', PzCurrCurrencyEnum::BRL);

        // toFloat() é exatamente (float) getAmount(): mesma semântica, sem repetição.
        $this->assertSame((float) $m->getAmount(), $m->toFloat());
    }

    public function test_to_float_does_not_mutate_amount(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', PzCurrCurrencyEnum::BRL);

        $m->toFloat();

        // A string permanece a fonte de verdade, intacta após a conversão.
        $this->assertSame('19.90', $m->getAmount());
        $this->assertSame(2, $m->getScale());
    }

    public function test_get_amount_remains_exact_string_for_high_scale(): void
    {
        // Documenta o motivo de getAmount() ser a fonte canônica: a string preserva
        // todos os dígitos (escala 10), enquanto float poderia perder precisão.
        $m = (new PzCurrBcMath())->of('1.0640000001', PzCurrCurrencyEnum::BRL, 10);

        $this->assertSame('1.0640000001', $m->getAmount());
        $this->assertIsFloat($m->toFloat());
    }
}
