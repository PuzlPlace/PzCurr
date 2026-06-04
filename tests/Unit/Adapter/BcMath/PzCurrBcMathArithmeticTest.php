<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;

final class PzCurrBcMathArithmeticTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Precisão: sem problemas de float
    // -------------------------------------------------------------------------

    public function test_add_classic_float_precision_issue(): void
    {
        // 0.1 + 0.2 seria 0.30000000000000004 em aritmética float
        $m = (new PzCurrBcMath())->of('0.1', 'BRL')->add('0.2');

        $this->assertSame('0.30', $m->getAmount());
    }

    public function test_no_float_in_result(): void
    {
        $m = (new PzCurrBcMath())->of('0.1', 'BRL')->add('0.2');

        // Confirma string exata '0.30', não representação float
        $this->assertIsString($m->getAmount());
        $this->assertSame('0.30', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // add()
    // -------------------------------------------------------------------------

    public function test_add_single_value(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->add('5.00');

        $this->assertSame('15.00', $m->getAmount());
    }

    public function test_add_variadic_multiple_values(): void
    {
        $m = (new PzCurrBcMath())->of('0.00', 'BRL')->add('1', '2', '3');

        $this->assertSame('6.00', $m->getAmount());
    }

    public function test_add_integer_operand(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->add(5);

        $this->assertSame('15.00', $m->getAmount());
    }

    public function test_add_pzcurr_instance_operand(): void
    {
        $a = (new PzCurrBcMath())->of('10.00', 'BRL');
        $b = (new PzCurrBcMath())->of('3.50', 'BRL');

        $a->add($b);

        $this->assertSame('13.50', $a->getAmount());
    }

    public function test_add_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL');
        $result = $m->add('5.00');

        $this->assertSame($m, $result);
    }

    public function test_add_currency_mismatch_throws(): void
    {
        $brl = (new PzCurrBcMath())->of('1.00', 'BRL');
        $usd = (new PzCurrBcMath())->of('1.00', 'USD');

        $this->expectException(PzCurrencyMismatchException::class);
        $brl->add($usd);
    }

    public function test_add_invalid_string_throws(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);
        (new PzCurrBcMath())->of('10.00', 'BRL')->add('abc');
    }

    // -------------------------------------------------------------------------
    // subtract()
    // -------------------------------------------------------------------------

    public function test_subtract_single_value(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->subtract('3.00');

        $this->assertSame('7.00', $m->getAmount());
    }

    public function test_subtract_variadic_multiple_values(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->subtract('1', '2', '3');

        $this->assertSame('4.00', $m->getAmount());
    }

    public function test_subtract_to_negative(): void
    {
        $m = (new PzCurrBcMath())->of('5.00', 'BRL')->subtract('10.00');

        $this->assertSame('-5.00', $m->getAmount());
    }

    public function test_subtract_pzcurr_instance_operand(): void
    {
        $a = (new PzCurrBcMath())->of('10.00', 'BRL');
        $b = (new PzCurrBcMath())->of('3.00', 'BRL');

        $a->subtract($b);

        $this->assertSame('7.00', $a->getAmount());
    }

    public function test_subtract_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL');
        $result = $m->subtract('5.00');

        $this->assertSame($m, $result);
    }

    public function test_subtract_currency_mismatch_throws(): void
    {
        $brl = (new PzCurrBcMath())->of('5.00', 'BRL');
        $usd = (new PzCurrBcMath())->of('1.00', 'USD');

        $this->expectException(PzCurrencyMismatchException::class);
        $brl->subtract($usd);
    }

    // -------------------------------------------------------------------------
    // multiply()
    // -------------------------------------------------------------------------

    public function test_multiply_integer(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->multiply(3);

        $this->assertSame('30.00', $m->getAmount());
    }

    public function test_multiply_decimal(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->multiply('1.5');

        $this->assertSame('15.00', $m->getAmount());
    }

    public function test_multiply_negative(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->multiply('-1');

        $this->assertSame('-10.00', $m->getAmount());
    }

    public function test_multiply_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('5.00', 'BRL');
        $result = $m->multiply(2);

        $this->assertSame($m, $result);
    }

    public function test_multiply_invalid_factor_throws(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);
        (new PzCurrBcMath())->of('10.00', 'BRL')->multiply('x');
    }

    /**
     * @return array<string, array{string, PzCurrRoundingModeEnum, string}>
     *
     * All cases: start with 0.10, multiply by factor → 0.0XX, round at scale=2.
     * Factor 0.15 → 0.015 (guide=5); factor 0.11 → 0.011 (guide=1); factor 0.19 → 0.019 (guide=9).
     */
    public static function multiplyRoundingProvider(): array
    {
        return [
            'half_up rounds up on 5'     => ['0.15', PzCurrRoundingModeEnum::HALF_UP,   '0.02'],
            'half_down rounds down on 5' => ['0.15', PzCurrRoundingModeEnum::HALF_DOWN, '0.01'],
            'up rounds any excess'       => ['0.11', PzCurrRoundingModeEnum::UP,        '0.02'],
            'down truncates'             => ['0.19', PzCurrRoundingModeEnum::DOWN,      '0.01'],
            'ceiling positive'           => ['0.11', PzCurrRoundingModeEnum::CEILING,   '0.02'],
            'floor truncates positive'   => ['0.11', PzCurrRoundingModeEnum::FLOOR,     '0.01'],
        ];
    }

    #[DataProvider('multiplyRoundingProvider')]
    public function test_multiply_respects_rounding_mode(
        string $factor,
        PzCurrRoundingModeEnum $mode,
        string $expected,
    ): void {
        // 0.10 × factor yields a value that requires rounding at scale=2
        $m = (new PzCurrBcMath())->of('0.10', 'BRL')->multiply($factor, $mode);

        $this->assertSame($expected, $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // divide()
    // -------------------------------------------------------------------------

    public function test_divide_integer(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->divide(2);

        $this->assertSame('5.00', $m->getAmount());
    }

    public function test_divide_with_half_up_default(): void
    {
        // 10.00 / 3 = 3.3333... → HALF_UP at scale 2 → 3.33
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->divide(3);

        $this->assertSame('3.33', $m->getAmount());
    }

    public function test_divide_explicit_half_up(): void
    {
        // 1.00 / 3 = 0.3333... → HALF_UP → 0.33
        $m = (new PzCurrBcMath())->of('1.00', 'BRL')->divide(3, PzCurrRoundingModeEnum::HALF_UP);

        $this->assertSame('0.33', $m->getAmount());
    }

    public function test_divide_explicit_ceiling(): void
    {
        // 1.00 / 3 = 0.3333... → CEILING → 0.34
        $m = (new PzCurrBcMath())->of('1.00', 'BRL')->divide(3, PzCurrRoundingModeEnum::CEILING);

        $this->assertSame('0.34', $m->getAmount());
    }

    public function test_divide_explicit_floor(): void
    {
        // 1.00 / 3 = 0.3333... → FLOOR → 0.33
        $m = (new PzCurrBcMath())->of('1.00', 'BRL')->divide(3, PzCurrRoundingModeEnum::FLOOR);

        $this->assertSame('0.33', $m->getAmount());
    }

    public function test_divide_explicit_up(): void
    {
        // 1.00 / 3 = 0.3333... → UP → 0.34
        $m = (new PzCurrBcMath())->of('1.00', 'BRL')->divide(3, PzCurrRoundingModeEnum::UP);

        $this->assertSame('0.34', $m->getAmount());
    }

    public function test_divide_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL');
        $result = $m->divide(2);

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // mod()
    // -------------------------------------------------------------------------

    public function test_mod_returns_remainder(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->mod(3);

        $this->assertSame('1.00', $m->getAmount());
    }

    public function test_mod_divisible_evenly(): void
    {
        $m = (new PzCurrBcMath())->of('9.00', 'BRL')->mod(3);

        $this->assertSame('0.00', $m->getAmount());
    }

    public function test_mod_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL');
        $result = $m->mod(3);

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // absolute()
    // -------------------------------------------------------------------------

    public function test_absolute_of_negative(): void
    {
        $m = (new PzCurrBcMath())->of('-5.00', 'BRL')->absolute();

        $this->assertSame('5.00', $m->getAmount());
    }

    public function test_absolute_of_positive_unchanged(): void
    {
        $m = (new PzCurrBcMath())->of('5.00', 'BRL')->absolute();

        $this->assertSame('5.00', $m->getAmount());
    }

    public function test_absolute_of_zero_unchanged(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL')->absolute();

        $this->assertTrue($m->isZero());
    }

    public function test_absolute_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('-5.00', 'BRL');
        $result = $m->absolute();

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // negated()
    // -------------------------------------------------------------------------

    public function test_negated_positive_becomes_negative(): void
    {
        $m = (new PzCurrBcMath())->of('5.00', 'BRL')->negated();

        $this->assertSame('-5.00', $m->getAmount());
    }

    public function test_negated_negative_becomes_positive(): void
    {
        $m = (new PzCurrBcMath())->of('-5.00', 'BRL')->negated();

        $this->assertSame('5.00', $m->getAmount());
    }

    public function test_negated_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('5.00', 'BRL');
        $result = $m->negated();

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // ratioOf()
    // -------------------------------------------------------------------------

    public function test_ratio_of_returns_string(): void
    {
        $a = (new PzCurrBcMath())->of('3.00', 'BRL');
        $b = (new PzCurrBcMath())->of('12.00', 'BRL');

        $ratio = $a->ratioOf($b);

        // Escala de trabalho = escala da moeda (2) + margem (10) = 12 casas decimais
        $this->assertIsString($ratio);
        $this->assertSame('0.250000000000', $ratio);
    }

    public function test_ratio_of_currency_mismatch_throws(): void
    {
        $brl = (new PzCurrBcMath())->of('5.00', 'BRL');
        $usd = (new PzCurrBcMath())->of('10.00', 'USD');

        $this->expectException(PzCurrencyMismatchException::class);
        $brl->ratioOf($usd);
    }

    // -------------------------------------------------------------------------
    // withScale()
    // -------------------------------------------------------------------------

    public function test_with_scale_increases_precision(): void
    {
        $m = (new PzCurrBcMath())->of('1.23', 'BRL')->withScale(4);

        $this->assertSame(4, $m->getScale());
        $this->assertSame('1.23', $m->getAmount());
    }

    public function test_with_scale_decreases_precision_with_rounding(): void
    {
        $m = (new PzCurrBcMath())->of('1.235', 'BRL')->withScale(2);

        // HALF_UP na escala 2: 1.235 → 1.24
        $this->assertSame('1.24', $m->getAmount());
        $this->assertSame(2, $m->getScale());
    }

    public function test_with_scale_accepts_explicit_mode(): void
    {
        // Aumenta escala para 4, adiciona valor que arredondaria diferente conforme o modo.
        // 1.00 + 0.2359 = 1.2359 na escala=4
        // Dígito guia = 5: HALF_UP → '1.24', DOWN → '1.23'
        $m = (new PzCurrBcMath())
            ->of('1.00', 'BRL')
            ->withScale(4)
            ->add('0.2359')
            ->withScale(2, PzCurrRoundingModeEnum::DOWN);

        $this->assertSame('1.23', $m->getAmount());
    }

    public function test_with_scale_returns_same_instance(): void
    {
        $m = (new PzCurrBcMath())->of('1.00', 'BRL');
        $result = $m->withScale(4);

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // Encadeamento fluente
    // -------------------------------------------------------------------------

    public function test_fluent_chain_order_calculation(): void
    {
        // 25.00 + 4.99 - 2.50 * 2 = (27.49) * 2 = 54.98
        $m = (new PzCurrBcMath())
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->subtract('2.50')
            ->multiply(2);

        $this->assertSame('54.98', $m->getAmount());
    }

    public function test_fluent_chain_each_step_returns_same_instance(): void
    {
        $m     = (new PzCurrBcMath())->of('10.00', 'BRL');
        $after = $m->add('5.00')->subtract('2.00')->multiply(2);

        $this->assertSame($m, $after);
    }

    // -------------------------------------------------------------------------
    // withRoundingMode()
    // -------------------------------------------------------------------------

    public function test_with_rounding_mode_changes_default(): void
    {
        $m = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->withRoundingMode(PzCurrRoundingModeEnum::DOWN)
            ->divide(3);

        // HALF_DOWN: 3.333... → 3.33 (trunca)
        $this->assertSame('3.33', $m->getAmount());
    }
}
