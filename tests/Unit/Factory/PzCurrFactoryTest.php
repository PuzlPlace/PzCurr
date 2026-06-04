<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Contract\PzCurrInterface;
use Puzl\PzCurr\Factory\PzCurrAdapterEnum;
use Puzl\PzCurr\Factory\PzCurrFactory;

final class PzCurrFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('PZCURR_ADAPTER');
    }

    protected function tearDown(): void
    {
        putenv('PZCURR_ADAPTER');
    }

    // -------------------------------------------------------------------------
    // Default (no arguments, no config, no env)
    // -------------------------------------------------------------------------

    public function testMakeWithoutArgumentsReturnsBcMath(): void
    {
        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
        self::assertInstanceOf(PzCurrInterface::class, $instance);
    }

    // -------------------------------------------------------------------------
    // Argumento explícito
    // -------------------------------------------------------------------------

    public function testMakeWithExplicitBcmathAdapterReturnsBcMath(): void
    {
        $instance = PzCurrFactory::make(PzCurrAdapterEnum::BCMATH);

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    // -------------------------------------------------------------------------
    // Fallback silencioso
    // -------------------------------------------------------------------------

    public function testMakeWithBrickMoneyFallsBackToBcMathSilently(): void
    {
        $instance = PzCurrFactory::make(PzCurrAdapterEnum::BRICK_MONEY);

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    public function testMakeWithMoneyPhpFallsBackToBcMathSilently(): void
    {
        $instance = PzCurrFactory::make(PzCurrAdapterEnum::MONEYPHP);

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    // -------------------------------------------------------------------------
    // Resolução por variável de ambiente
    // -------------------------------------------------------------------------

    public function testMakeResolvesFromEnvBcmath(): void
    {
        putenv('PZCURR_ADAPTER=BCMATH');

        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    public function testMakeResolvesFromEnvBrickMoneyFallsBackToBcMath(): void
    {
        putenv('PZCURR_ADAPTER=BRICK_MONEY');

        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    public function testMakeWithInvalidEnvValueFallsBackToBcMath(): void
    {
        putenv('PZCURR_ADAPTER=FOO');

        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    public function testMakeWithEmptyEnvFallsBackToBcMath(): void
    {
        putenv('PZCURR_ADAPTER=');

        $instance = PzCurrFactory::make();

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }

    // -------------------------------------------------------------------------
    // Argumento explícito sobrepõe env
    // -------------------------------------------------------------------------

    public function testExplicitArgumentOverridesEnv(): void
    {
        putenv('PZCURR_ADAPTER=MONEYPHP');

        $instance = PzCurrFactory::make(PzCurrAdapterEnum::BCMATH);

        self::assertInstanceOf(PzCurrBcMath::class, $instance);
    }
}
