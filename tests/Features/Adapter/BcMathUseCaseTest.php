<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;

/**
 * End-to-end integration tests for PzCurrBcMath.
 *
 * These tests exercise the adapter in realistic use-case scenarios,
 * verifying the full calculation pipeline without any Laravel infrastructure.
 */
final class BcMathUseCaseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Use case: order total calculation
    // -------------------------------------------------------------------------

    public function test_order_total_calculation(): void
    {
        // Base price 25.00, add shipping 4.99, subtract discount 2.50, quantity 2
        $total = (new PzCurrBcMath())
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->subtract('2.50')
            ->multiply(2);

        $this->assertSame('54.98', $total->getAmount());
        $this->assertSame('BRL', $total->getCurrency()->code);
    }

    // -------------------------------------------------------------------------
    // Use case: division with default HALF_UP rounding
    // -------------------------------------------------------------------------

    public function test_division_with_default_half_up_rounding(): void
    {
        $result = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->divide(3);

        $this->assertSame('3.33', $result->getAmount());
    }

    // -------------------------------------------------------------------------
    // Use case: repeated sum keeps precision
    // -------------------------------------------------------------------------

    public function test_sum_of_many_small_amounts(): void
    {
        // 100 × 0.10 should be exactly 10.00
        $m = (new PzCurrBcMath())->zero('BRL');

        for ($i = 0; $i < 100; $i++) {
            $m->add('0.10');
        }

        $this->assertSame('10.00', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // Use case: copy and branch calculation
    // -------------------------------------------------------------------------

    public function test_copy_allows_branched_calculation(): void
    {
        $base  = (new PzCurrBcMath())->of('100.00', 'BRL');
        $withTax    = $base->copy()->multiply('1.1');
        $withoutTax = $base->copy();

        $this->assertSame('110.00', $withTax->getAmount());
        $this->assertSame('100.00', $withoutTax->getAmount());
        $this->assertSame('100.00', $base->getAmount());
    }

    // -------------------------------------------------------------------------
    // Use case: negative balance scenario
    // -------------------------------------------------------------------------

    public function test_negative_balance_operations(): void
    {
        $balance = (new PzCurrBcMath())
            ->of('50.00', 'BRL')
            ->subtract('75.00');

        $this->assertSame('-25.00', $balance->getAmount());
        $this->assertTrue($balance->isNegative());
        $this->assertSame(-2500, $balance->getMinorAmount());
    }

    // -------------------------------------------------------------------------
    // Use case: scale conversion
    // -------------------------------------------------------------------------

    public function test_scale_change_after_operations(): void
    {
        $m = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->divide(3, PzCurrRoundingModeEnum::HALF_UP)
            ->withScale(4, PzCurrRoundingModeEnum::HALF_UP);

        $this->assertSame(4, $m->getScale());
        // After BRL divide(3) → '3.33', then withScale(4) → '3.33' (no excess to round)
        $this->assertSame('3.33', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // Use case: toArray / jsonSerialize
    // -------------------------------------------------------------------------

    public function test_to_array_structure(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', 'BRL');

        $array = $m->toArray();

        $this->assertSame([
            'amount'   => '19.90',
            'currency' => 'BRL',
            'scale'    => 2,
        ], $array);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $m = (new PzCurrBcMath())->of('99.99', 'USD');

        $this->assertSame($m->toArray(), $m->jsonSerialize());
    }

    public function test_json_encode_produces_valid_json(): void
    {
        $m    = (new PzCurrBcMath())->of('9.99', 'BRL');
        $json = json_encode($m);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);

        $this->assertSame('9.99', $decoded['amount']);
        $this->assertSame('BRL', $decoded['currency']);
        $this->assertSame(2, $decoded['scale']);
    }
}
