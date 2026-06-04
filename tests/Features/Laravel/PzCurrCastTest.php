<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Laravel;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Factory\PzCurrFactory;
use Puzl\PzCurr\Laravel\PzCurrCast;

final class PzCurrCastTest extends TestCase
{
    private CastTestModel $model;
    private PzCurrCast $cast;

    protected function setUp(): void
    {
        $this->model = new CastTestModel();
        $this->cast  = new PzCurrCast();
    }

    // -------------------------------------------------------------------------
    // set() — decompõe em 2 colunas sem float
    // -------------------------------------------------------------------------

    public function test_set_returns_two_string_columns(): void
    {
        $money  = PzCurrFactory::make()->of('19.90', 'BRL');
        $result = $this->cast->set($this->model, 'price', $money, []);

        self::assertArrayHasKey('price_amount', $result);
        self::assertArrayHasKey('price_currency', $result);
        self::assertSame('19.90', $result['price_amount']);
        self::assertSame('BRL', $result['price_currency']);
    }

    public function test_set_amount_is_string_not_float(): void
    {
        $money  = PzCurrFactory::make()->of('19.90', 'BRL');
        $result = $this->cast->set($this->model, 'price', $money, []);

        self::assertIsString($result['price_amount']);
        self::assertNotSame(19.9, $result['price_amount']);
    }

    public function test_set_throws_for_non_pzcurr_value(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);

        $this->cast->set($this->model, 'price', 'invalid', []);
    }

    public function test_set_throws_for_null_value(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);

        $this->cast->set($this->model, 'price', null, []);
    }

    public function test_set_throws_for_numeric_value(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);

        $this->cast->set($this->model, 'price', 19.90, []);
    }

    // -------------------------------------------------------------------------
    // get() — reconstrói PzCurrInterface a partir das 2 colunas
    // -------------------------------------------------------------------------

    public function test_get_reconstructs_pzcurr_from_two_columns(): void
    {
        $result = $this->cast->get($this->model, 'price', null, [
            'price_amount'   => '19.90',
            'price_currency' => 'BRL',
        ]);

        self::assertInstanceOf(PzCurrInterface::class, $result);
        self::assertSame('19.90', $result->getAmount());
        self::assertSame('BRL', $result->getCurrency()->code);
    }

    public function test_get_returns_null_when_amount_column_missing(): void
    {
        $result = $this->cast->get($this->model, 'price', null, [
            'price_currency' => 'BRL',
        ]);

        self::assertNull($result);
    }

    public function test_get_returns_null_when_currency_column_missing(): void
    {
        $result = $this->cast->get($this->model, 'price', null, [
            'price_amount' => '19.90',
        ]);

        self::assertNull($result);
    }

    public function test_get_returns_null_when_both_columns_missing(): void
    {
        $result = $this->cast->get($this->model, 'price', null, []);

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Round-trip: set → get preserva valor exato sem float
    // -------------------------------------------------------------------------

    public function test_round_trip_preserves_amount_and_currency(): void
    {
        $original = PzCurrFactory::make()->of('19.90', 'BRL');

        $columns = $this->cast->set($this->model, 'price', $original, []);

        $reconstructed = $this->cast->get($this->model, 'price', null, $columns);

        self::assertNotNull($reconstructed);
        self::assertSame('19.90', $reconstructed->getAmount());
        self::assertSame('BRL', $reconstructed->getCurrency()->code);
    }

    public function test_round_trip_with_high_precision_amount(): void
    {
        $original = PzCurrFactory::make()->of('1234567.89', 'USD');

        $columns = $this->cast->set($this->model, 'total', $original, []);

        self::assertSame('1234567.89', $columns['total_amount']);
        self::assertSame('USD', $columns['total_currency']);

        $reconstructed = $this->cast->get($this->model, 'total', null, $columns);

        self::assertNotNull($reconstructed);
        self::assertSame('1234567.89', $reconstructed->getAmount());
    }

    public function test_round_trip_with_jpy_zero_scale(): void
    {
        $original = PzCurrFactory::make()->of('1000', 'JPY');

        $columns = $this->cast->set($this->model, 'amount', $original, []);

        self::assertSame('USD', 'USD');
        self::assertSame('1000', $columns['amount_amount']);
        self::assertSame('JPY', $columns['amount_currency']);

        $reconstructed = $this->cast->get($this->model, 'amount', null, $columns);

        self::assertNotNull($reconstructed);
        self::assertSame('JPY', $reconstructed->getCurrency()->code);
    }

    public function test_set_uses_different_key_prefix(): void
    {
        $money  = PzCurrFactory::make()->of('50.00', 'EUR');
        $result = $this->cast->set($this->model, 'shipping_cost', $money, []);

        self::assertArrayHasKey('shipping_cost_amount', $result);
        self::assertArrayHasKey('shipping_cost_currency', $result);
        self::assertSame('50.00', $result['shipping_cost_amount']);
        self::assertSame('EUR', $result['shipping_cost_currency']);
    }
}

/**
 * Minimal model stub to avoid depending on Eloquent ORM in tests.
 */
final class CastTestModel
{
}
