<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;

/**
 * Integration tests for the output layer: format() + serialize() after a chain
 * of arithmetic operations.
 */
final class OutputIntegrationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Full pipeline: arithmetic → format
    // -------------------------------------------------------------------------

    public function test_format_after_arithmetic_pipeline(): void
    {
        // 25.00 + 4.99 = 29.99 − 2.50 = 27.49 × 2 = 54.98
        $result = (new PzCurrBcMath())
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->subtract('2.50')
            ->multiply(2)
            ->format();

        $this->assertSame('R$ 54,98', $result);
    }

    public function test_format_after_add_with_thousands(): void
    {
        $result = (new PzCurrBcMath())
            ->of('1000.00', 'BRL')
            ->add('234.56')
            ->format();

        $this->assertSame('R$ 1.234,56', $result);
    }

    public function test_format_after_divide(): void
    {
        $result = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->divide(4)
            ->format();

        $this->assertSame('R$ 2,50', $result);
    }

    // -------------------------------------------------------------------------
    // Serialization round-trip
    // -------------------------------------------------------------------------

    public function test_json_round_trip_after_arithmetic(): void
    {
        $pz = (new PzCurrBcMath())
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->subtract('2.50')
            ->multiply(2);

        $json    = json_encode($pz, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('54.98', $decoded['amount']);
        $this->assertSame('BRL', $decoded['currency']);
        $this->assertSame(2, $decoded['scale']);
    }

    public function test_to_array_is_stable_after_arithmetic(): void
    {
        $pz = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->add('5.00')
            ->subtract('2.50');

        $array = $pz->toArray();

        $this->assertSame('12.50', $array['amount']);
        $this->assertSame('BRL', $array['currency']);
        $this->assertSame(2, $array['scale']);
    }

    public function test_json_encode_amount_is_string_after_pipeline(): void
    {
        $pz   = (new PzCurrBcMath())->of('25.00', 'BRL')->add('4.99');
        $json = json_encode($pz, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"amount":"29.99"', $json);
    }

    // -------------------------------------------------------------------------
    // getMinorAmount after arithmetic
    // -------------------------------------------------------------------------

    public function test_get_minor_amount_after_arithmetic(): void
    {
        $pz = (new PzCurrBcMath())
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->subtract('2.50')
            ->multiply(2);

        $this->assertSame(5498, $pz->getMinorAmount());
    }

    public function test_reconstruct_from_minor_amount(): void
    {
        $original = (new PzCurrBcMath())->of('19.90', 'BRL');
        $minor    = $original->getMinorAmount();

        $reconstructed = (new PzCurrBcMath())->ofMinor($minor, 'BRL');

        $this->assertSame($original->getAmount(), $reconstructed->getAmount());
    }

    // -------------------------------------------------------------------------
    // JPY pipeline (scale=0)
    // -------------------------------------------------------------------------

    public function test_jpy_pipeline_format_and_serialize(): void
    {
        $pz = (new PzCurrBcMath())
            ->of('1000', 'JPY')
            ->add('234');

        $this->assertSame('¥ 1.234', $pz->format());

        $array = $pz->toArray();
        $this->assertSame('1234', $array['amount']);
        $this->assertSame('JPY', $array['currency']);
        $this->assertSame(0, $array['scale']);
    }
}
