<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;

final class PzCurrBcMathOutputTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getMinorAmount
    // -------------------------------------------------------------------------

    public function test_get_minor_amount_brl(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame(1990, $pz->getMinorAmount());
    }

    public function test_get_minor_amount_integer(): void
    {
        $pz = (new PzCurrBcMath())->of('10.00', 'BRL');

        $this->assertSame(1000, $pz->getMinorAmount());
    }

    public function test_get_minor_amount_jpy_scale_zero(): void
    {
        $pz = (new PzCurrBcMath())->of('1234', 'JPY');

        $this->assertSame(1234, $pz->getMinorAmount());
    }

    public function test_get_minor_amount_returns_int_type(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertIsInt($pz->getMinorAmount());
    }

    public function test_get_minor_amount_zero(): void
    {
        $pz = (new PzCurrBcMath())->zero('BRL');

        $this->assertSame(0, $pz->getMinorAmount());
    }

    public function test_get_minor_amount_three_scale(): void
    {
        $pz = (new PzCurrBcMath())->of('1.500', 'KWD');

        $this->assertSame(1500, $pz->getMinorAmount());
    }

    // -------------------------------------------------------------------------
    // getAmount / toDecimal
    // -------------------------------------------------------------------------

    public function test_get_amount_returns_string(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertIsString($pz->getAmount());
        $this->assertSame('19.90', $pz->getAmount());
    }

    public function test_to_decimal_is_alias_of_get_amount(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame($pz->getAmount(), $pz->toDecimal());
    }

    // -------------------------------------------------------------------------
    // getScale / getCurrency
    // -------------------------------------------------------------------------

    public function test_get_scale_brl(): void
    {
        $pz = (new PzCurrBcMath())->of('10.00', 'BRL');

        $this->assertSame(2, $pz->getScale());
    }

    public function test_get_currency_brl(): void
    {
        $pz = (new PzCurrBcMath())->of('10.00', 'BRL');

        $this->assertSame('BRL', $pz->getCurrency()->code);
    }

    // -------------------------------------------------------------------------
    // toArray / jsonSerialize — structure
    // -------------------------------------------------------------------------

    public function test_to_array_structure_brl(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame(
            ['amount' => '19.90', 'currency' => 'BRL', 'scale' => 2],
            $pz->toArray(),
        );
    }

    public function test_to_array_amount_is_string(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $array = $pz->toArray();

        $this->assertIsString($array['amount']);
    }

    public function test_to_array_scale_is_int(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $array = $pz->toArray();

        $this->assertIsInt($array['scale']);
    }

    public function test_to_array_no_float_values(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $array = $pz->toArray();

        // Todos os valores devem ser string ou int — nunca float.
        $this->assertIsString($array['amount']);
        $this->assertIsString($array['currency']);
        $this->assertIsInt($array['scale']);
        $this->assertNotSame('double', gettype($array['amount']));
        $this->assertNotSame('double', gettype($array['scale']));
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame($pz->toArray(), $pz->jsonSerialize());
    }

    public function test_json_encode_produces_correct_json(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $json   = json_encode($pz, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('19.90', $decoded['amount']);
        $this->assertSame('BRL', $decoded['currency']);
        $this->assertSame(2, $decoded['scale']);
    }

    public function test_json_encode_amount_is_string_not_number(): void
    {
        $pz   = (new PzCurrBcMath())->of('19.90', 'BRL');
        $json = json_encode($pz, JSON_THROW_ON_ERROR);

        // Amount deve ser string JSON ("19.90"), não número.
        $this->assertStringContainsString('"amount":"19.90"', $json);
    }

    // -------------------------------------------------------------------------
    // format() via adapter
    // -------------------------------------------------------------------------

    public function test_format_brl_via_adapter(): void
    {
        $pz = (new PzCurrBcMath())->of('1234.56', 'BRL');

        $this->assertSame('R$ 1.234,56', $pz->format());
    }

    public function test_format_brl_simple_via_adapter(): void
    {
        $pz = (new PzCurrBcMath())->of('19.90', 'BRL');

        $this->assertSame('R$ 19,90', $pz->format());
    }

    public function test_format_jpy_via_adapter(): void
    {
        $pz = (new PzCurrBcMath())->of('1234', 'JPY');

        $this->assertSame('¥ 1.234', $pz->format());
    }
}
