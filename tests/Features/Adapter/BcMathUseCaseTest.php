<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Features\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Adapter\BcMath\PzCurrBcMath;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;

/**
 * Testes de integração end-to-end do PzCurrBcMath.
 *
 * Exercita o adapter em cenários realistas, verificando o pipeline completo
 * sem infraestrutura Laravel.
 */
final class BcMathUseCaseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Caso de uso: total de pedido
    // -------------------------------------------------------------------------

    public function test_order_total_calculation(): void
    {
        // Preço base 25.00 + frete 4.99 − desconto 2.50, quantidade 2
        $total = (new PzCurrBcMath())
            ->of('25.00', 'BRL')
            ->add('4.99')
            ->subtract('2.50')
            ->multiply(2);

        $this->assertSame('54.98', $total->getAmount());
        $this->assertSame('BRL', $total->getCurrency()->code);
    }

    // -------------------------------------------------------------------------
    // Caso de uso: divisão com HALF_UP padrão
    // -------------------------------------------------------------------------

    public function test_division_with_default_half_up_rounding(): void
    {
        $result = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->divide(3);

        $this->assertSame('3.33', $result->getAmount());
    }

    // -------------------------------------------------------------------------
    // Caso de uso: soma repetida mantém precisão
    // -------------------------------------------------------------------------

    public function test_sum_of_many_small_amounts(): void
    {
        // 100 × 0.10 deve ser exatamente 10.00
        $m = (new PzCurrBcMath())->zero('BRL');

        for ($i = 0; $i < 100; $i++) {
            $m->add('0.10');
        }

        $this->assertSame('10.00', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // Caso de uso: copy() e ramificação de cálculo
    // -------------------------------------------------------------------------

    public function test_copy_allows_branched_calculation(): void
    {
        $base  = (new PzCurrBcMath())->of('100.00', 'BRL');
        $withTax    = $base->copy()->multiply('1.1');
        $withoutTax = $base->copy();

        $this->assertSame('110.00', $withTax->getAmount());
        $this->assertSame('100.00', $withoutTax->getAmount());
        $this->assertSame('100.00', $base->getAmount());
    }

    // -------------------------------------------------------------------------
    // Caso de uso: saldo negativo
    // -------------------------------------------------------------------------

    public function test_negative_balance_operations(): void
    {
        $balance = (new PzCurrBcMath())
            ->of('50.00', 'BRL')
            ->subtract('75.00');

        $this->assertSame('-25.00', $balance->getAmount());
        $this->assertTrue($balance->isNegative());
        $this->assertSame(-2500, $balance->getMinorAmount());
    }

    // -------------------------------------------------------------------------
    // Caso de uso: conversão de escala
    // -------------------------------------------------------------------------

    public function test_scale_change_after_operations(): void
    {
        $m = (new PzCurrBcMath())
            ->of('10.00', 'BRL')
            ->divide(3, PzCurrRoundingModeEnum::HALF_UP)
            ->withScale(4, PzCurrRoundingModeEnum::HALF_UP);

        $this->assertSame(4, $m->getScale());
        // Após divide(3) → '3.33', withScale(4) → '3.33' (sem excesso a arredondar)
        $this->assertSame('3.33', $m->getAmount());
    }

    // -------------------------------------------------------------------------
    // Caso de uso: toArray / jsonSerialize
    // -------------------------------------------------------------------------

    public function test_to_array_structure(): void
    {
        $m = (new PzCurrBcMath())->of('19.90', 'BRL');

        $array = $m->toArray();

        $this->assertSame([
            'amount'   => '19.90',
            'currency' => 'BRL',
            'scale'    => 2,
        ], $array);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $m = (new PzCurrBcMath())->of('99.99', 'USD');

        $this->assertSame($m->toArray(), $m->jsonSerialize());
    }

    public function test_json_encode_produces_valid_json(): void
    {
        $m    = (new PzCurrBcMath())->of('9.99', 'BRL');
        $json = json_encode($m);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);

        $this->assertSame('9.99', $decoded['amount']);
        $this->assertSame('BRL', $decoded['currency']);
        $this->assertSame(2, $decoded['scale']);
    }
}
