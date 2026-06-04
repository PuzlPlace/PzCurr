<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter\BcMath;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Currency\PzCurrCurrencyRegistry;
use Puzl\PzCurr\Enum\PzCurrCurrencyEnum;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrInvalidScaleException;

/**
 * Cobre a escala opcional no of()/zero() e o uso do enum PzCurrCurrencyEnum,
 * mantendo a moeda padrão (BRL) quando nenhuma é informada.
 */
final class PzCurrBcMathScaleAndEnumTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Moeda padrão (BRL) quando não informada
    // -------------------------------------------------------------------------

    public function test_of_without_currency_defaults_to_brl(): void
    {
        $m = (new PzCurrBcMath())->of('1.50');

        $this->assertSame('BRL', $m->getCurrency()->code);
        $this->assertSame(2, $m->getScale());
        $this->assertSame('1.50', $m->getAmount());
    }

    public function test_zero_without_currency_defaults_to_brl(): void
    {
        $m = (new PzCurrBcMath())->zero();

        $this->assertSame('BRL', $m->getCurrency()->code);
        $this->assertTrue($m->isZero());
    }

    // -------------------------------------------------------------------------
    // Moeda via enum
    // -------------------------------------------------------------------------

    public function test_of_accepts_currency_enum(): void
    {
        $m = (new PzCurrBcMath())->of('10.00', PzCurrCurrencyEnum::USD);

        $this->assertSame('USD', $m->getCurrency()->code);
        $this->assertSame(2, $m->getScale());
    }

    public function test_of_enum_jpy_uses_zero_scale(): void
    {
        $m = (new PzCurrBcMath())->of('1000', PzCurrCurrencyEnum::JPY);

        $this->assertSame('JPY', $m->getCurrency()->code);
        $this->assertSame(0, $m->getScale());
    }

    public function test_enum_string_and_default_are_equivalent(): void
    {
        $fromEnum   = (new PzCurrBcMath())->of('5.00', PzCurrCurrencyEnum::BRL);
        $fromString = (new PzCurrBcMath())->of('5.00', 'BRL');
        $fromNull   = (new PzCurrBcMath())->of('5.00');

        $this->assertSame($fromEnum->getAmount(), $fromString->getAmount());
        $this->assertSame($fromEnum->getCurrency()->code, $fromNull->getCurrency()->code);
    }

    public function test_every_enum_case_resolves_in_registry(): void
    {
        foreach (PzCurrCurrencyEnum::cases() as $case) {
            $currency = PzCurrCurrencyRegistry::of($case->value);
            $this->assertSame($case->value, $currency->code);
        }
    }

    // -------------------------------------------------------------------------
    // Escala explícita prevalece sobre a escala da moeda
    // -------------------------------------------------------------------------

    public function test_of_with_explicit_scale_overrides_currency_scale(): void
    {
        $m = (new PzCurrBcMath())->of('1.1234567', PzCurrCurrencyEnum::BRL, 7);

        $this->assertSame(7, $m->getScale());
        $this->assertSame('1.1234567', $m->getAmount());
    }

    public function test_of_without_scale_uses_currency_scale(): void
    {
        // Sem scale → BRL arredonda para 2 casas (HALF_UP)
        $m = (new PzCurrBcMath())->of('1.1234567', PzCurrCurrencyEnum::BRL);

        $this->assertSame(2, $m->getScale());
        $this->assertSame('1.12', $m->getAmount());
    }

    public function test_explicit_scale_zero_on_two_scale_currency(): void
    {
        $m = (new PzCurrBcMath())->of('1.6', PzCurrCurrencyEnum::BRL, 0);

        $this->assertSame(0, $m->getScale());
        $this->assertSame('2', $m->getAmount());
    }

    public function test_zero_accepts_explicit_scale(): void
    {
        $m = (new PzCurrBcMath())->zero(PzCurrCurrencyEnum::BRL, 7);

        $this->assertSame(7, $m->getScale());
        $this->assertTrue($m->isZero());

        // A escala definida é preservada nas operações subsequentes.
        $m->add('0.0000001');
        $this->assertSame('0.0000001', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // Operações respeitam a escala definida na instância
    // -------------------------------------------------------------------------

    public function test_operations_respect_explicit_scale(): void
    {
        $m = (new PzCurrBcMath())
            ->of('1.0000000', PzCurrCurrencyEnum::BRL, 7)
            ->add('0.0000001')
            ->multiply('2');

        $this->assertSame(7, $m->getScale());
        $this->assertSame('2.0000002', $m->getAmount());
    }

    public function test_nfe_use_case_unit_price_times_quantity_then_two_decimals(): void
    {
        // Unitário com 10 casas × quantidade, reduzindo vProd para 2 casas no fim.
        $vProd = (new PzCurrBcMath())
            ->of('1.0640000000', PzCurrCurrencyEnum::BRL, 10)
            ->multiply('39680.1234')
            ->withScale(2);

        $this->assertSame(2, $vProd->getScale());
        $this->assertSame('42219.65', $vProd->getAmount());
    }

    // -------------------------------------------------------------------------
    // Validação de escala
    // -------------------------------------------------------------------------

    public function test_of_negative_scale_throws_exception(): void
    {
        $this->expectException(PzCurrInvalidScaleException::class);
        (new PzCurrBcMath())->of('1.00', PzCurrCurrencyEnum::BRL, -1);
    }

    public function test_with_scale_negative_throws_exception(): void
    {
        $this->expectException(PzCurrInvalidScaleException::class);
        (new PzCurrBcMath())->of('1.00', PzCurrCurrencyEnum::BRL)->withScale(-2);
    }

    public function test_of_explicit_scale_keeps_rounding_mode(): void
    {
        // scale 4, valor com 5ª casa = 5 → HALF_UP sobe
        $m = (new PzCurrBcMath(PzCurrRoundingModeEnum::HALF_UP))->of('1.23455', PzCurrCurrencyEnum::BRL, 4);

        $this->assertSame('1.2346', $m->getAmount());
    }
}
