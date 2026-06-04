<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Factory;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Factory\PzCurrAdapterEnum;
use Puzl\PzCurr\Factory\PzCurrFactory;

final class FactoryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('PZCURR_ADAPTER');
    }

    protected function tearDown(): void
    {
        putenv('PZCURR_ADAPTER');
    }

    /**
     * Caso de uso 5.2: pipeline completo via Factory sem conhecer o adapter concreto.
     */
    public function testPipelineViaFactory(): void
    {
        $result = PzCurrFactory::make()
            ->of('25.00', 'BRL')
            ->add('4.99');

        self::assertSame('29.99', $result->getAmount());
        self::assertSame('BRL', $result->getCurrency()->code);
    }

    public function testFactoryReturnsOperationalInstance(): void
    {
        $instance = PzCurrFactory::make();

        $result = $instance
            ->of('100.00', 'BRL')
            ->subtract('10.00')
            ->multiply('2');

        self::assertSame('180.00', $result->getAmount());
    }

    /**
     * Resolução por config simulada: BRICK_MONEY no env cai silenciosamente em BCMath.
     * Consumidor recebe BCMath sem precisar saber do fallback.
     */
    public function testFactoryWithBrickMoneyEnvFallsBackAndOperatesCorrectly(): void
    {
        putenv('PZCURR_ADAPTER=BRICK_MONEY');

        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);

        $result = $instance->of('10.00', 'BRL')->add('5.00');

        self::assertSame('15.00', $result->getAmount());
    }

    /**
     * Argumento explícito BCMATH → instância correta executa operação fim-a-fim.
     */
    public function testExplicitAdapterEndToEnd(): void
    {
        $result = PzCurrFactory::make(PzCurrAdapterEnum::BCMATH)
            ->of('50.00', 'USD')
            ->divide('3');

        self::assertSame('16.67', $result->getAmount());
    }

    /**
     * Factory funciona em ambiente standalone (sem Laravel / sem config()).
     */
    public function testFactoryWorksWithoutLaravelConfig(): void
    {
        // config() não existe neste ambiente de testes standalone
        self::assertFalse(function_exists('config'));

        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }
}
