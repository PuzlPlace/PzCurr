<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;

final class PzCurrBcMathComparisonTest extends TestCase
{
    // -------------------------------------------------------------------------
    // compareTo()
    // -------------------------------------------------------------------------

    public function test_compare_to_equal(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertSame(0, $a->compareTo($b));
    }

    public function test_compare_to_greater(): void
    {
        $a = (new PzCurrBcMath())->of('10.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertSame(1, $a->compareTo($b));
    }

    public function test_compare_to_less(): void
    {
        $a = (new PzCurrBcMath())->of('3.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertSame(-1, $a->compareTo($b));
    }

    public function test_compare_to_string(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertSame(0, $a->compareTo('5.00'));
        $this->assertSame(1, $a->compareTo('4.00'));
        $this->assertSame(-1, $a->compareTo('6.00'));
    }

    public function test_compare_to_integer(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertSame(0, $a->compareTo(5));
        $this->assertSame(1, $a->compareTo(4));
        $this->assertSame(-1, $a->compareTo(6));
    }

    public function test_compare_to_currency_mismatch_throws(): void
    {
        $brl = (new PzCurrBcMath())->of('5.00', 'BRL');
        $usd = (new PzCurrBcMath())->of('5.00', 'USD');

        $this->expectException(PzCurrencyMismatchException::class);
        $brl->compareTo($usd);
    }

    // -------------------------------------------------------------------------
    // isEqualTo()
    // -------------------------------------------------------------------------

    public function test_is_equal_to_same_value(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isEqualTo($b));
    }

    public function test_is_equal_to_different_value(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('6.00', 'BRL');

        $this->assertFalse($a->isEqualTo($b));
    }

    public function test_is_equal_to_string(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isEqualTo('5'));
        $this->assertFalse($a->isEqualTo('5.01'));
    }

    // -------------------------------------------------------------------------
    // isGreaterThan() / isGreaterThanOrEqualTo()
    // -------------------------------------------------------------------------

    public function test_is_greater_than_true(): void
    {
        $a = (new PzCurrBcMath())->of('10.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isGreaterThan($b));
    }

    public function test_is_greater_than_false_for_equal(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertFalse($a->isGreaterThan($b));
    }

    public function test_is_greater_than_or_equal_to_for_equal(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isGreaterThanOrEqualTo($b));
    }

    public function test_is_greater_than_or_equal_to_for_greater(): void
    {
        $a = (new PzCurrBcMath())->of('6.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isGreaterThanOrEqualTo($b));
    }

    public function test_is_greater_than_or_equal_to_false_for_less(): void
    {
        $a = (new PzCurrBcMath())->of('4.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertFalse($a->isGreaterThanOrEqualTo($b));
    }

    // -------------------------------------------------------------------------
    // isLessThan() / isLessThanOrEqualTo()
    // -------------------------------------------------------------------------

    public function test_is_less_than_true(): void
    {
        $a = (new PzCurrBcMath())->of('3.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isLessThan($b));
    }

    public function test_is_less_than_false_for_equal(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertFalse($a->isLessThan($b));
    }

    public function test_is_less_than_or_equal_to_for_equal(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isLessThanOrEqualTo($b));
    }

    public function test_is_less_than_or_equal_to_for_less(): void
    {
        $a = (new PzCurrBcMath())->of('4.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isLessThanOrEqualTo($b));
    }

    public function test_is_less_than_or_equal_to_false_for_greater(): void
    {
        $a = (new PzCurrBcMath())->of('6.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertFalse($a->isLessThanOrEqualTo($b));
    }

    // -------------------------------------------------------------------------
    // isZero()
    // -------------------------------------------------------------------------

    public function test_is_zero_for_zero(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertTrue($m->isZero());
    }

    public function test_is_zero_false_for_positive(): void
    {
        $m = (new PzCurrBcMath())->of('0.01', 'BRL');

        $this->assertFalse($m->isZero());
    }

    public function test_is_zero_false_for_negative(): void
    {
        $m = (new PzCurrBcMath())->of('-0.01', 'BRL');

        $this->assertFalse($m->isZero());
    }

    // -------------------------------------------------------------------------
    // isPositive() / isPositiveOrZero()
    // -------------------------------------------------------------------------

    public function test_is_positive_for_positive_value(): void
    {
        $m = (new PzCurrBcMath())->of('1.00', 'BRL');

        $this->assertTrue($m->isPositive());
    }

    public function test_is_positive_false_for_zero(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertFalse($m->isPositive());
    }

    public function test_is_positive_false_for_negative(): void
    {
        $m = (new PzCurrBcMath())->of('-1.00', 'BRL');

        $this->assertFalse($m->isPositive());
    }

    public function test_is_positive_or_zero_for_zero(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertTrue($m->isPositiveOrZero());
    }

    public function test_is_positive_or_zero_for_positive(): void
    {
        $m = (new PzCurrBcMath())->of('1.00', 'BRL');

        $this->assertTrue($m->isPositiveOrZero());
    }

    public function test_is_positive_or_zero_false_for_negative(): void
    {
        $m = (new PzCurrBcMath())->of('-0.01', 'BRL');

        $this->assertFalse($m->isPositiveOrZero());
    }

    // -------------------------------------------------------------------------
    // isNegative() / isNegativeOrZero()
    // -------------------------------------------------------------------------

    public function test_is_negative_for_negative_value(): void
    {
        $m = (new PzCurrBcMath())->of('-1.00', 'BRL');

        $this->assertTrue($m->isNegative());
    }

    public function test_is_negative_false_for_zero(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertFalse($m->isNegative());
    }

    public function test_is_negative_false_for_positive(): void
    {
        $m = (new PzCurrBcMath())->of('1.00', 'BRL');

        $this->assertFalse($m->isNegative());
    }

    public function test_is_negative_or_zero_for_zero(): void
    {
        $m = (new PzCurrBcMath())->zero('BRL');

        $this->assertTrue($m->isNegativeOrZero());
    }

    public function test_is_negative_or_zero_for_negative(): void
    {
        $m = (new PzCurrBcMath())->of('-1.00', 'BRL');

        $this->assertTrue($m->isNegativeOrZero());
    }

    public function test_is_negative_or_zero_false_for_positive(): void
    {
        $m = (new PzCurrBcMath())->of('0.01', 'BRL');

        $this->assertFalse($m->isNegativeOrZero());
    }

    // -------------------------------------------------------------------------
    // getSign()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function signProvider(): array
    {
        return [
            'positive'      => ['5.00',  1],
            'zero'          => ['0.00',  0],
            'negative'      => ['-5.00', -1],
            'small positive'=> ['0.01',  1],
            'small negative'=> ['-0.01', -1],
        ];
    }

    #[DataProvider('signProvider')]
    public function test_get_sign(string $amount, int $expectedSign): void
    {
        $m = (new PzCurrBcMath())->of($amount, 'BRL');

        $this->assertSame($expectedSign, $m->getSign());
    }

    // -------------------------------------------------------------------------
    // isSameValueAs()
    // -------------------------------------------------------------------------

    public function test_is_same_value_as_same_currency_same_value(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('5.00', 'BRL');

        $this->assertTrue($a->isSameValueAs($b));
    }

    public function test_is_same_value_as_same_currency_different_value(): void
    {
        $a = (new PzCurrBcMath())->of('5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('6.00', 'BRL');

        $this->assertFalse($a->isSameValueAs($b));
    }

    public function test_is_same_value_as_different_currency_returns_false_no_exception(): void
    {
        $brl = (new PzCurrBcMath())->of('5.00', 'BRL');
        $usd = (new PzCurrBcMath())->of('5.00', 'USD');

        // Must NOT throw PzCurrencyMismatchException — returns false instead
        $this->assertFalse($brl->isSameValueAs($usd));
    }

    public function test_is_same_value_as_zero_values_same_currency(): void
    {
        $a = (new PzCurrBcMath())->zero('BRL');
        $b = (new PzCurrBcMath())->zero('BRL');

        $this->assertTrue($a->isSameValueAs($b));
    }

    public function test_is_same_value_as_negative_values(): void
    {
        $a = (new PzCurrBcMath())->of('-5.00', 'BRL');
        $b = (new PzCurrBcMath())->of('-5.00', 'BRL');

        $this->assertTrue($a->isSameValueAs($b));
    }
}
