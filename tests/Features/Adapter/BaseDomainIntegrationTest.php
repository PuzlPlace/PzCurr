<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Exception\PzCurrencyMismatchException;
use Puzl\PzCurr\Tests\Unit\Adapter\PzCurrBaseStub;

/**
 * Testes de integração: PzCurrBase + PzCurrCurrencyRegistry + PzCurrRoundingModeEnum.
 */
class BaseDomainIntegrationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Cenário obrigatório: Base resolve moeda do Registry e escala
    // -------------------------------------------------------------------------

    public function test_base_resolves_jpy_currency_and_scale_from_registry(): void
    {
        $stub = new PzCurrBaseStub();
        $stub->of('100', 'JPY');

        $this->assertSame(0, $stub->getScale());
        $this->assertSame('JPY', $stub->getCurrency()->code);
    }

    public function test_base_resolves_brl_currency_and_scale_from_registry(): void
    {
        $stub = new PzCurrBaseStub();
        $stub->of('100', 'BRL');

        $this->assertSame(2, $stub->getScale());
        $this->assertSame('BRL', $stub->getCurrency()->code);
    }

    public function test_base_resolves_usd_currency_and_scale_from_registry(): void
    {
        $stub = new PzCurrBaseStub();
        $stub->of('50', 'USD');

        $this->assertSame(2, $stub->getScale());
        $this->assertSame('USD', $stub->getCurrency()->code);
    }

    // -------------------------------------------------------------------------
    // Serialização integrada com o Registry
    // -------------------------------------------------------------------------

    public function test_toArray_integrates_with_registry_currency(): void
    {
        $stub = new PzCurrBaseStub();
        $stub->of('19.90', 'BRL');

        $expected = ['amount' => '19.90', 'currency' => 'BRL', 'scale' => 2];

        $this->assertSame($expected, $stub->toArray());
        $this->assertSame($expected, $stub->jsonSerialize());
    }

    // -------------------------------------------------------------------------
    // Implementa PzCurrInterface
    // -------------------------------------------------------------------------

    public function test_stub_implements_pzcurr_interface(): void
    {
        $stub = new PzCurrBaseStub();

        $this->assertInstanceOf(PzCurrInterface::class, $stub);
    }

    // -------------------------------------------------------------------------
    // Mismatch entre moedas reais do Registry
    // -------------------------------------------------------------------------

    public function test_mismatch_between_registry_currencies_throws(): void
    {
        $brl = (new PzCurrBaseStub())->of('100', 'BRL');
        $usd = (new PzCurrBaseStub())->of('100', 'USD');

        $this->expectException(PzCurrencyMismatchException::class);
        $brl->testAssertSameCurrency($usd);
    }

    // -------------------------------------------------------------------------
    // copy() independente com moedas do Registry
    // -------------------------------------------------------------------------

    public function test_copy_independence_with_registry_currencies(): void
    {
        $original = (new PzCurrBaseStub())->of('200', 'EUR');
        $clone    = $original->copy();

        $clone->of('999', 'EUR');

        $this->assertSame('200', $original->getAmount());
        $this->assertSame('999', $clone->getAmount());
    }
}
