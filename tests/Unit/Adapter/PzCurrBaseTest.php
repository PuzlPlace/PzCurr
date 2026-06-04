<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;

/**
 * @covers \Puzl\PzCurr\Adapter\PzCurrBase
 */
class PzCurrBaseTest extends TestCase
{
    private function stub(): PzCurrBaseStub
    {
        return new PzCurrBaseStub();
    }

    // -------------------------------------------------------------------------
    // Fluência — retorna $this
    // -------------------------------------------------------------------------

    public function test_of_returns_same_instance(): void
    {
        $pz     = $this->stub();
        $result = $pz->of('1', 'BRL');

        $this->assertSame($pz, $result);
    }

    public function test_withRoundingMode_returns_same_instance(): void
    {
        $pz     = $this->stub()->of('1', 'BRL');
        $result = $pz->withRoundingMode(PzCurrRoundingModeEnum::HALF_UP);

        $this->assertSame($pz, $result);
    }

    public function test_fluent_chain_of_add_subtract_returns_same_instance(): void
    {
        $pz = $this->stub()->of('1', 'BRL');

        $this->assertSame($pz, $pz->add('1'));
        $this->assertSame($pz, $pz->subtract('1'));
        $this->assertSame($pz, $pz->multiply('2'));
        $this->assertSame($pz, $pz->divide('2'));
        $this->assertSame($pz, $pz->mod('3'));
        $this->assertSame($pz, $pz->absolute());
        $this->assertSame($pz, $pz->negated());
        $this->assertSame($pz, $pz->withScale(4));
    }

    // -------------------------------------------------------------------------
    // copy() — instância independente
    // -------------------------------------------------------------------------

    public function test_copy_returns_new_instance(): void
    {
        $a = $this->stub()->of('10', 'BRL');
        $b = $a->copy();

        $this->assertNotSame($a, $b);
    }

    public function test_copy_preserves_amount(): void
    {
        $a = $this->stub()->of('10', 'BRL');
        $b = $a->copy();

        $this->assertSame($a->getAmount(), $b->getAmount());
    }

    public function test_copy_is_independent_from_original(): void
    {
        $a = $this->stub()->of('10', 'BRL');
        $b = $a->copy();

        $b->of('99', 'BRL');

        $this->assertSame('10', $a->getAmount());
        $this->assertSame('99', $b->getAmount());
    }

    public function test_copy_preserves_currency(): void
    {
        $a = $this->stub()->of('5', 'USD');
        $b = $a->copy();

        $this->assertSame('USD', $b->getCurrency()->code);
    }

    public function test_copy_preserves_scale(): void
    {
        $a = $this->stub()->of('1', 'BRL');
        $b = $a->copy();

        $this->assertSame(2, $b->getScale());
    }

    // -------------------------------------------------------------------------
    // Validação de moeda (RN-02)
    // -------------------------------------------------------------------------

    public function test_assert_same_currency_throws_on_mismatch(): void
    {
        $brl = $this->stub()->of('1', 'BRL');
        $usd = $this->stub()->of('1', 'USD');

        $this->expectException(PzCurrencyMismatchException::class);
        $brl->testAssertSameCurrency($usd);
    }

    public function test_assert_same_currency_does_not_throw_when_equal(): void
    {
        $a = $this->stub()->of('1', 'BRL');
        $b = $this->stub()->of('2', 'BRL');

        $a->testAssertSameCurrency($b);

        $this->assertTrue(true);
    }

    public function test_mismatch_exception_message_contains_both_currencies(): void
    {
        $brl = $this->stub()->of('1', 'BRL');
        $eur = $this->stub()->of('1', 'EUR');

        try {
            $brl->testAssertSameCurrency($eur);
            $this->fail('Expected PzCurrencyMismatchException was not thrown.');
        } catch (PzCurrencyMismatchException $e) {
            $this->assertStringContainsString('BRL', $e->getMessage());
            $this->assertStringContainsString('EUR', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // toArray / jsonSerialize
    // -------------------------------------------------------------------------

    public function test_toArray_returns_correct_structure(): void
    {
        $pz = $this->stub()->of('19.90', 'BRL');

        $this->assertSame(
            ['amount' => '19.90', 'currency' => 'BRL', 'scale' => 2],
            $pz->toArray(),
        );
    }

    public function test_jsonSerialize_matches_toArray(): void
    {
        $pz = $this->stub()->of('19.90', 'BRL');

        $this->assertSame($pz->toArray(), $pz->jsonSerialize());
    }

    public function test_toArray_with_jpy_has_scale_zero(): void
    {
        $pz = $this->stub()->of('1000', 'JPY');

        $this->assertSame(
            ['amount' => '1000', 'currency' => 'JPY', 'scale' => 0],
            $pz->toArray(),
        );
    }

    // -------------------------------------------------------------------------
    // Getters básicos
    // -------------------------------------------------------------------------

    public function test_getAmount_returns_string(): void
    {
        $pz = $this->stub()->of('5.50', 'USD');

        $this->assertSame('5.50', $pz->getAmount());
    }

    public function test_toDecimal_is_alias_of_getAmount(): void
    {
        $pz = $this->stub()->of('100', 'EUR');

        $this->assertSame($pz->getAmount(), $pz->toDecimal());
    }

    public function test_toFloat_casts_amount_string_to_float(): void
    {
        $pz = $this->stub()->of('123.45', 'BRL');

        $this->assertIsFloat($pz->toFloat());
        $this->assertSame(123.45, $pz->toFloat());
        // Não muta a string fonte de verdade.
        $this->assertSame('123.45', $pz->getAmount());
    }

    public function test_getCurrency_returns_correct_code(): void
    {
        $pz = $this->stub()->of('1', 'BRL');

        $this->assertSame('BRL', $pz->getCurrency()->code);
    }

    public function test_getScale_returns_currency_scale(): void
    {
        $pz = $this->stub()->of('1', 'BRL');

        $this->assertSame(2, $pz->getScale());
    }

    public function test_getScale_returns_zero_for_jpy(): void
    {
        $pz = $this->stub()->of('1', 'JPY');

        $this->assertSame(0, $pz->getScale());
    }

    // -------------------------------------------------------------------------
    // withRoundingMode (efeito observável via copy)
    // -------------------------------------------------------------------------

    public function test_withRoundingMode_affects_cloned_copy(): void
    {
        $pz = $this->stub()->of('1', 'BRL');
        $pz->withRoundingMode(PzCurrRoundingModeEnum::FLOOR);

        $copy = $pz->copy();

        $this->assertNotSame($pz, $copy);
        $this->assertSame($pz->getAmount(), $copy->getAmount());
        $this->assertSame($pz->getCurrency()->code, $copy->getCurrency()->code);
    }

    // -------------------------------------------------------------------------
    // getCurrency() antes de of() deve lançar
    // -------------------------------------------------------------------------

    public function test_getCurrency_throws_logic_exception_when_uninitialized(): void
    {
        $pz = $this->stub();

        $this->expectException(\LogicException::class);
        $pz->getCurrency();
    }
}
