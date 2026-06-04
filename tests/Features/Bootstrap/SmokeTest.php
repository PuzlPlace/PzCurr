<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Bootstrap;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Factory\PzCurrFactory;
use Puzl\PzCurr\Laravel\PzCurrCast;
use Puzl\PzCurr\Laravel\PzCurrServiceProvider;

final class SmokeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Smoke E2E — caso de uso principal
    // -------------------------------------------------------------------------

    /**
     * Cenário obrigatório da Task 8.0:
     * PzCurrFactory::make()->of('25.00','BRL')->add('4.99')->format() === 'R$ 29,99'
     */
    public function test_smoke_e2e_factory_of_add_format(): void
    {
        $result = PzCurrFactory::make()
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->format();

        self::assertSame('R$ 29,99', $result);
    }

    public function test_smoke_service_provider_class_exists(): void
    {
        self::assertTrue(class_exists(PzCurrServiceProvider::class));
    }

    public function test_smoke_cast_class_exists(): void
    {
        self::assertTrue(class_exists(PzCurrCast::class));
    }

    public function test_smoke_full_pipeline_with_cast(): void
    {
        $cast  = new PzCurrCast();
        $model = new \stdClass();

        $money   = PzCurrFactory::make()->of('25.00', 'BRL')->add('4.99');
        $columns = $cast->set($model, 'price', $money, []);

        self::assertSame('29.99', $columns['price_amount']);
        self::assertSame('BRL', $columns['price_currency']);

        $reconstructed = $cast->get($model, 'price', null, $columns);

        self::assertNotNull($reconstructed);
        self::assertSame('R$ 29,99', $reconstructed->format());
    }
}
