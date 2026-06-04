<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Factory\PzCurrAdapterEnum;

final class PzCurrAdapterEnumTest extends TestCase
{
    public function testEnumCasesValues(): void
    {
        self::assertSame(0, PzCurrAdapterEnum::BCMATH->value);
        self::assertSame(1, PzCurrAdapterEnum::BRICK_MONEY->value);
        self::assertSame(2, PzCurrAdapterEnum::MONEYPHP->value);
    }

    public function testTryFromNameBcmath(): void
    {
        $result = PzCurrAdapterEnum::tryFromName('BCMATH');

        self::assertSame(PzCurrAdapterEnum::BCMATH, $result);
    }

    public function testTryFromNameBrickMoney(): void
    {
        $result = PzCurrAdapterEnum::tryFromName('BRICK_MONEY');

        self::assertSame(PzCurrAdapterEnum::BRICK_MONEY, $result);
    }

    public function testTryFromNameMoneyPhp(): void
    {
        $result = PzCurrAdapterEnum::tryFromName('MONEYPHP');

        self::assertSame(PzCurrAdapterEnum::MONEYPHP, $result);
    }

    public function testTryFromNameCaseSensitiveLowerReturnsNull(): void
    {
        $result = PzCurrAdapterEnum::tryFromName('bcmath');

        self::assertNull($result);
    }

    public function testTryFromNameUnknownReturnsNull(): void
    {
        $result = PzCurrAdapterEnum::tryFromName('FOO');

        self::assertNull($result);
    }

    public function testTryFromNameEmptyStringReturnsNull(): void
    {
        $result = PzCurrAdapterEnum::tryFromName('');

        self::assertNull($result);
    }

    public function testAllCasesArePresent(): void
    {
        $cases = PzCurrAdapterEnum::cases();

        self::assertCount(3, $cases);
    }
}
