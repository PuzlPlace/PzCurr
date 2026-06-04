<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;

/**
 * Guards against degenerate inputs that would otherwise leak native PHP errors
 * (DivisionByZeroError, TypeError) or silently corrupt values (int overflow).
 */
final class PzCurrBcMathGuardsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Division by zero — typed exceptions instead of native DivisionByZeroError
    // -------------------------------------------------------------------------

    public function test_divide_zero_throws_typed_exception(): void
    {
        $this->expectException(PzCurrDivisionByZeroException::class);

        (new PzCurrBcMath())->of('10.00', 'BRL')->divide(0);
    }

    public function test_divide_zero_decimal_string_throws(): void
    {
        $this->expectException(PzCurrDivisionByZeroException::class);

        (new PzCurrBcMath())->of('10.00', 'BRL')->divide('0.00');
    }

    public function test_mod_by_zero_throws_typed_exception(): void
    {
        $this->expectException(PzCurrDivisionByZeroException::class);

        (new PzCurrBcMath())->of('10.00', 'BRL')->mod(0);
    }

    public function test_ratio_of_zero_throws_typed_exception(): void
    {
        $base  = (new PzCurrBcMath())->of('10.00', 'BRL');
        $other = (new PzCurrBcMath())->of('0.00', 'BRL');

        $this->expectException(PzCurrDivisionByZeroException::class);

        $base->ratioOf($other);
    }

    public function test_valid_division_still_works(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->divide(4);

        $this->assertSame('2.50', $m->getAmount());
    }

    public function test_valid_mod_still_works(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', 'BRL')->mod(3);

        $this->assertSame('1.00', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // getMinorAmount overflow guard
    // -------------------------------------------------------------------------

    public function test_get_minor_amount_overflow_throws(): void
    {
        $m = (new PzCurrBcMath())->of('99999999999999999999.99', 'BRL');

        $this->expectException(PzCurrInvalidAmountException::class);

        $m->getMinorAmount();
    }

    public function test_get_minor_amount_within_int_range_works(): void
    {
        $m = (new PzCurrBcMath())->of('1990.00', 'BRL');

        $this->assertSame(199000, $m->getMinorAmount());
    }

    public function test_get_minor_amount_negative_within_range_works(): void
    {
        $m = (new PzCurrBcMath())->of('-1.50', 'BRL');

        $this->assertSame(-150, $m->getMinorAmount());
    }
}
