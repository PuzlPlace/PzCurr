<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;
use Puzl\PzCurr\Support\PzCurrAllocator;

final class PzCurrAllocatorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // split — divisão exata
    // -------------------------------------------------------------------------

    public function test_split_exact_two_parts(): void
    {
        $parts = PzCurrAllocator::split('10.00', 2, 2);

        $this->assertSame(['10.00', '5.00', '5.00'][1], $parts[0]);
        $this->assertSame('5.00', $parts[0]);
        $this->assertSame('5.00', $parts[1]);
        $this->assertSame('10.00', bcadd($parts[0], $parts[1], 2));
    }

    // -------------------------------------------------------------------------
    // split — com resto (conservação de centavos)
    // -------------------------------------------------------------------------

    public function test_split_with_remainder_three_parts(): void
    {
        $parts = PzCurrAllocator::split('10.00', 3, 2);

        $this->assertCount(3, $parts);
        // Primeira parte recebe o centavo extra.
        $this->assertSame('3.34', $parts[0]);
        $this->assertSame('3.33', $parts[1]);
        $this->assertSame('3.33', $parts[2]);
        // Conservação.
        $sum = array_reduce($parts, fn (string $carry, string $v) => bcadd($carry, $v, 2), '0.00');
        $this->assertSame('10.00', $sum);
    }

    // -------------------------------------------------------------------------
    // allocate — ratios [1,1,1]
    // -------------------------------------------------------------------------

    public function test_allocate_equal_ratios_conservation(): void
    {
        $parts = PzCurrAllocator::allocate('100.00', [1, 1, 1], 2);

        $this->assertCount(3, $parts);
        $sum = array_reduce($parts, fn (string $carry, string $v) => bcadd($carry, $v, 2), '0.00');
        $this->assertSame('100.00', $sum);
    }

    // -------------------------------------------------------------------------
    // allocate — ratios [7,3] com resto sub-centavo
    // -------------------------------------------------------------------------

    public function test_allocate_seven_three_ratios(): void
    {
        $parts = PzCurrAllocator::allocate('0.05', [7, 3], 2);

        $this->assertCount(2, $parts);
        $sum = bcadd($parts[0], $parts[1], 2);
        $this->assertSame('0.05', $sum);
        // Parte com ratio 7 recebe o centavo extra (maior resto).
        $this->assertSame('0.04', $parts[0]);
        $this->assertSame('0.01', $parts[1]);
    }

    // -------------------------------------------------------------------------
    // Independência de ordem: multisetas idênticas para qualquer ordenação
    // -------------------------------------------------------------------------

    public function test_allocate_order_independent_multiset(): void
    {
        // Com [7,3]: ratio 7 recebe centavo extra → {0.04, 0.01}
        // Com [3,7]: ratio 7 ainda vence empate → {0.01, 0.04}
        // Multisetas ordenadas devem ser idênticas.
        $ratios1 = [3, 7];
        $ratios2 = [7, 3];

        $parts1 = PzCurrAllocator::allocate('0.05', $ratios1, 2);
        $parts2 = PzCurrAllocator::allocate('0.05', $ratios2, 2);

        sort($parts1);
        sort($parts2);

        $this->assertSame($parts1, $parts2, 'Multiset must be identical regardless of ratio order.');

        $sum1 = array_reduce($parts1, fn (string $c, string $v) => bcadd($c, $v, 2), '0.00');
        $sum2 = array_reduce($parts2, fn (string $c, string $v) => bcadd($c, $v, 2), '0.00');
        $this->assertSame($sum1, $sum2);
    }

    public function test_allocate_order_independent_three_ratios(): void
    {
        $amounts  = ['100.00'];
        $orders   = [
            [1, 2, 3],
            [3, 1, 2],
            [2, 3, 1],
            [3, 2, 1],
        ];

        foreach ($amounts as $amount) {
            $sums = [];
            foreach ($orders as $ratios) {
                $parts  = PzCurrAllocator::allocate($amount, $ratios, 2);
                $sorted = $parts;
                sort($sorted);
                $sum    = array_reduce($parts, fn (string $c, string $v) => bcadd($c, $v, 2), '0.00');
                $sums[] = $sum;
            }
            $this->assertCount(1, array_unique($sums), "Sum must be {$amount} for all orderings.");
        }
    }

    // -------------------------------------------------------------------------
    // Valores negativos
    // -------------------------------------------------------------------------

    public function test_split_negative_conservation(): void
    {
        $parts = PzCurrAllocator::split('-10.00', 3, 2);

        $this->assertCount(3, $parts);
        $sum = array_reduce($parts, fn (string $carry, string $v) => bcadd($carry, $v, 2), '0.00');
        $this->assertSame('-10.00', $sum);
        // Todas as partes devem ser negativas.
        foreach ($parts as $part) {
            $this->assertLessThan(0, bccomp($part, '0', 2));
        }
    }

    // -------------------------------------------------------------------------
    // Chaves associativas preservadas
    // -------------------------------------------------------------------------

    public function test_allocate_preserves_associative_keys(): void
    {
        $parts = PzCurrAllocator::allocate('10.00', ['a' => 1, 'b' => 1], 2);

        $this->assertArrayHasKey('a', $parts);
        $this->assertArrayHasKey('b', $parts);
        $this->assertSame('5.00', $parts['a']);
        $this->assertSame('5.00', $parts['b']);
    }

    public function test_allocate_preserves_string_keys_with_remainder(): void
    {
        $parts = PzCurrAllocator::allocate('10.00', ['x' => 2, 'y' => 1], 2);

        $this->assertArrayHasKey('x', $parts);
        $this->assertArrayHasKey('y', $parts);
        $sum = bcadd($parts['x'], $parts['y'], 2);
        $this->assertSame('10.00', $sum);
    }

    // -------------------------------------------------------------------------
    // Escala 0 (JPY)
    // -------------------------------------------------------------------------

    public function test_split_scale_zero_jpy(): void
    {
        $parts = PzCurrAllocator::split('100', 3, 0);

        $this->assertSame('34', $parts[0]);
        $this->assertSame('33', $parts[1]);
        $this->assertSame('33', $parts[2]);
        $sum = array_reduce($parts, fn (string $c, string $v) => bcadd($c, $v, 0), '0');
        $this->assertSame('100', $sum);
    }

    // -------------------------------------------------------------------------
    // Ratios float convertidos com segurança
    // -------------------------------------------------------------------------

    public function test_allocate_float_ratios_conservation(): void
    {
        $parts = PzCurrAllocator::allocate('10.00', [0.5, 0.5], 2);

        $this->assertCount(2, $parts);
        $sum = bcadd($parts[0], $parts[1], 2);
        $this->assertSame('10.00', $sum);
    }

    // -------------------------------------------------------------------------
    // Cenários adicionais de conservação
    // -------------------------------------------------------------------------

    /** @dataProvider provideConservationCases */
    public function test_split_conservation(string $amount, int $parts, int $scale): void
    {
        $result = PzCurrAllocator::split($amount, $parts, $scale);

        $sum = array_reduce($result, fn (string $c, string $v) => bcadd($c, $v, $scale), bcdiv('0', '1', $scale));
        $this->assertSame(
            bcadd($amount, '0', $scale),
            $sum,
            "Conservation failed for amount={$amount} parts={$parts} scale={$scale}",
        );
    }

    /** @return array<string, array{string, int, int}> */
    public static function provideConservationCases(): array
    {
        return [
            'BRL 0.01 / 3'    => ['0.01', 3, 2],
            'BRL 1.00 / 7'    => ['1.00', 7, 2],
            'BRL 99.99 / 4'   => ['99.99', 4, 2],
            'JPY 10 / 3'      => ['10', 3, 0],
            'JPY 1 / 3'       => ['1', 3, 0],
            'Negative / 5'    => ['-7.77', 5, 2],
        ];
    }

    // -------------------------------------------------------------------------
    // Proteções contra entradas degeneradas
    // -------------------------------------------------------------------------

    public function test_allocate_with_zero_sum_ratios_throws(): void
    {
        $this->expectException(PzCurrDivisionByZeroException::class);

        PzCurrAllocator::allocate('10.00', [0, 0], 2);
    }

    public function test_allocate_with_cancelling_ratios_throws(): void
    {
        $this->expectException(PzCurrDivisionByZeroException::class);

        PzCurrAllocator::allocate('10.00', [1, -1], 2);
    }

    public function test_allocate_with_empty_ratios_throws(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);

        PzCurrAllocator::allocate('10.00', [], 2);
    }

    public function test_split_zero_parts_throws(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);

        PzCurrAllocator::split('10.00', 0, 2);
    }

    public function test_split_negative_parts_throws(): void
    {
        $this->expectException(PzCurrInvalidAmountException::class);

        PzCurrAllocator::split('10.00', -2, 2);
    }
}
