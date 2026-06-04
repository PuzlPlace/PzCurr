<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Exception\PzCurrInvalidCurrencyException;

final class PzCurrBcMathCreationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // of()
    // -------------------------------------------------------------------------

    public function test_of_string_amount_sets_value_and_currency(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame('19.90', $m->getAmount());
        $this->assertSame('BRL', $m->getCurrency()->code);
        $this->assertSame(2, $m->getScale());
    }

    public function test_of_integer_amount(): void
    {
        $m = (new PzCurrBcMath())->of(100, 'BRL');

        $this->assertSame('100', $m->getAmount());
        $this->assertSame('BRL', $m->getCurrency()->code);
    }

    public function test_of_zero_amount(): void
    {
        $m = (new PzCurrBcMath())->of('0', 'BRL');

        $this->assertSame('0', $m->getAmount());
        $this->assertTrue($m->isZero());
    }

    public function test_of_negative_amount(): void
    {
        $m = (new PzCurrBcMath())->of('-5.50', 'BRL');

        $this->assertSame('-5.50', $m->getAmount());
        $this->assertTrue($m->isNegative());
    }

    public function test_of_rounds_excess_decimals(): void
    {
        // '19.999' rounded at scale=2 with HALF_UP → '20.00'
        $m = (new PzCurrBcMath())->of('19.999', 'BRL');

        $this->assertSame('20.00', $m->getAmount());
    }

    public function test_of_usd_uses_correct_scale(): void
    {
        $m = (new PzCurrBcMath())->of('9.99', 'USD');

        $this->assertSame('9.99', $m->getAmount());
        $this->assertSame(2, $m->getScale());
    }

    public function test_of_jpy_uses_zero_scale(): void
    {
        $m = (new PzCurrBcMath())->of('1000', 'JPY');

        $this->assertSame('1000', $m->getAmount());
        $this->assertSame(0, $m->getScale());
    }

    public function test_of_invalid_string_throws_exception(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);
        (new PzCurrBcMath())->of('abc', 'BRL');
    }

    public function test_of_empty_string_throws_exception(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);
        (new PzCurrBcMath())->of('', 'BRL');
    }

    public function test_of_float_like_string_with_plus_sign_throws_exception(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);
        (new PzCurrBcMath())->of('+5.00', 'BRL');
    }

    public function test_of_invalid_currency_throws_exception(): void
    {
        $this->expectException(PzCurrInvalidCurrencyException::class);
        (new PzCurrBcMath())->of('10.00', 'XXX');
    }

    public function test_of_returns_same_instance(): void
    {
        $m = new PzCurrBcMath();
        $result = $m->of('10.00', 'BRL');

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // ofMinor()
    // -------------------------------------------------------------------------

    public function test_of_minor_converts_cents_to_major_unit(): void
    {
        $m = (new PzCurrBcMath())->ofMinor(1990, 'BRL');

        $this->assertSame('19.90', $m->getAmount());
        $this->assertSame('BRL', $m->getCurrency()->code);
    }

    public function test_of_minor_one_cent(): void
    {
        $m = (new PzCurrBcMath())->ofMinor(1, 'BRL');

        $this->assertSame('0.01', $m->getAmount());
    }

    public function test_of_minor_zero(): void
    {
        $m = (new PzCurrBcMath())->ofMinor(0, 'BRL');

        $this->assertSame('0.00', $m->getAmount());
    }

    public function test_of_minor_negative(): void
    {
        $m = (new PzCurrBcMath())->ofMinor(-500, 'BRL');

        $this->assertSame('-5.00', $m->getAmount());
    }

    public function test_of_minor_jpy_zero_scale(): void
    {
        // JPY has scale=0; 1000 minor units = 1000 yen
        $m = (new PzCurrBcMath())->ofMinor(1000, 'JPY');

        $this->assertSame('1000', $m->getAmount());
    }

    public function test_of_minor_usd_matches_of(): void
    {
        $fromMinor = (new PzCurrBcMath())->ofMinor(999, 'USD');
        $fromOf    = (new PzCurrBcMath())->of('9.99', 'USD');

        $this->assertSame($fromOf->getAmount(), $fromMinor->getAmount());
    }

    public function test_of_minor_returns_same_instance(): void
    {
        $m = new PzCurrBcMath();
        $result = $m->ofMinor(100, 'BRL');

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // zero()
    // -------------------------------------------------------------------------

    public function test_zero_creates_zero_value(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertSame('0', $m->getAmount());
        $this->assertTrue($m->isZero());
        $this->assertSame('BRL', $m->getCurrency()->code);
    }

    public function test_zero_returns_same_instance(): void
    {
        $m = new PzCurrBcMath();
        $result = $m->zero('BRL');

        $this->assertSame($m, $result);
    }

    // -------------------------------------------------------------------------
    // Constructor rounding mode
    // -------------------------------------------------------------------------

    public function test_constructor_default_mode_is_half_up(): void
    {
        // '1.005' at scale 2 with HALF_UP → '1.01' (guide digit 5)
        $m = (new PzCurrBcMath())->of('1.005', 'BRL');

        $this->assertSame('1.01', $m->getAmount());
    }

    public function test_constructor_accepts_custom_rounding_mode(): void
    {
        // '1.005' na escala 2 com HALF_DOWN → '1.00' (dígito guia 5 arredonda em direção a zero)
        $m = (new PzCurrBcMath(PzCurrRoundingModeEnum::HALF_DOWN))->of('1.005', 'BRL');

        $this->assertSame('1.00', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // getMinorAmount()
    // -------------------------------------------------------------------------

    public function test_get_minor_amount_returns_cents(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame(1990, $m->getMinorAmount());
    }

    public function test_get_minor_amount_zero(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertSame(0, $m->getMinorAmount());
    }

    public function test_get_minor_amount_jpy(): void
    {
        $m = (new PzCurrBcMath())->of('500', 'JPY');

        $this->assertSame(500, $m->getMinorAmount());
    }

    // -------------------------------------------------------------------------
    // copy()
    // -------------------------------------------------------------------------

    public function test_copy_creates_independent_instance(): void
    {
        $original = (new PzCurrBcMath())->of('10.00', 'BRL');
        $copy     = $original->copy();

        $copy->add('5.00');

        $this->assertSame('10.00', $original->getAmount());
        $this->assertSame('15.00', $copy->getAmount());
        $this->assertNotSame($original, $copy);
    }
}
