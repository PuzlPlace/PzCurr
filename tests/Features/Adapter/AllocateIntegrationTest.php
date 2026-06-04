<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Contract\PzCurrInterface;

final class AllocateIntegrationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // split — pedido rateado
    // -------------------------------------------------------------------------

    public function test_split_after_arithmetic_returns_valid_instances_with_exact_sum(): void
    {
        $total = (new PzCurrBcMath())->of('100.00', 'BRL')->add('0.01');
        $parts = $total->split(3);

        $this->assertCount(3, $parts);

        $sum = '0.00';
        foreach ($parts as $part) {
            $this->assertInstanceOf(PzCurrInterface::class, $part);
            $this->assertSame('BRL', $part->getCurrency()->code);
            $sum = bcadd($sum, $part->getAmount(), 2);
        }

        $this->assertSame('100.01', $sum, 'Sum of split parts must equal the original amount.');
    }

    public function test_split_parts_are_independent_copies(): void
    {
        $money = (new PzCurrBcMath())->of('10.00', 'BRL');
        $parts = $money->split(2);

        // Mutating one part must not affect the other or the original.
        $parts[0]->add('1.00');

        $this->assertSame('5.00', $parts[1]->getAmount());
        $this->assertSame('10.00', $money->getAmount());
    }

    // -------------------------------------------------------------------------
    // allocate — retorna PzCurr válidos com conservação
    // -------------------------------------------------------------------------

    public function test_allocate_returns_valid_pzcurr_with_correct_currency_and_sum(): void
    {
        $money = (new PzCurrBcMath())->of('0.05', 'BRL');
        $parts = $money->allocate([7, 3]);

        $this->assertCount(2, $parts);

        $sum = '0.00';
        foreach ($parts as $part) {
            $this->assertInstanceOf(PzCurrInterface::class, $part);
            $this->assertSame('BRL', $part->getCurrency()->code);
            $sum = bcadd($sum, $part->getAmount(), 2);
        }

        $this->assertSame('0.05', $sum, 'Sum of allocated parts must equal the original amount.');
    }

    public function test_allocate_preserves_associative_keys(): void
    {
        $money = (new PzCurrBcMath())->of('100.00', 'BRL');
        $parts = $money->allocate(['tax' => 1, 'fee' => 3, 'net' => 6]);

        $this->assertArrayHasKey('tax', $parts);
        $this->assertArrayHasKey('fee', $parts);
        $this->assertArrayHasKey('net', $parts);

        $sum = '0.00';
        foreach ($parts as $part) {
            $sum = bcadd($sum, $part->getAmount(), 2);
        }
        $this->assertSame('100.00', $sum);
    }

    public function test_allocate_parts_are_independent_copies(): void
    {
        $money = (new PzCurrBcMath())->of('10.00', 'BRL');
        $parts = $money->allocate([1, 1]);

        // Mutating first part must not affect the second.
        $parts[0]->add('100.00');

        $this->assertSame('5.00', $parts[1]->getAmount());
        $this->assertSame('10.00', $money->getAmount());
    }

    // -------------------------------------------------------------------------
    // Scale and currency preserved through split
    // -------------------------------------------------------------------------

    public function test_split_preserves_scale(): void
    {
        $money = (new PzCurrBcMath())->of('100', 'JPY');
        $parts = $money->split(3);

        $this->assertCount(3, $parts);
        foreach ($parts as $part) {
            $this->assertSame(0, $part->getScale());
            $this->assertSame('JPY', $part->getCurrency()->code);
        }

        $sum = array_reduce(
            $parts,
            fn (string $c, PzCurrInterface $p) => bcadd($c, $p->getAmount(), 0),
            '0',
        );
        $this->assertSame('100', $sum);
    }
}
