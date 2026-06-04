<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Support;

use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;

/**
 * Algoritmo do maior resto para distribuir valor monetário entre ratios.
 *
 * Visão geral:
 *  1. Converte amount para unidades menores (string inteira via bc*).
 *  2. Para cada ratio, calcula floor(totalMinor × ratio / totalRatios).
 *  3. Identifica restos fracionários e distribui unidades menores restantes.
 *  4. Converte cada parte de volta à escala decimal alvo.
 *
 * Garantias: soma das partes == amount; suporta negativos e escala 0; preserva chaves.
 */
final class PzCurrAllocator
{
    /** Casas decimais nas operações bc* intermediárias. */
    private const HIGH_SCALE = 40;

    /**
     * Distribui $amount entre os $ratios pelo método do maior resto.
     *
     * @param array<int|string, int|float|string> $ratios Pesos relativos; chaves preservadas.
     * @return array<int|string, string> Partes na escala alvo; Σ partes == $amount.
     */
    public static function allocate(string $amount, array $ratios, int $scale): array
    {
        $keys         = array_keys($ratios);
        $ratioStrings = array_map([self::class, 'ratioToString'], array_values($ratios));
        $count        = count($ratioStrings);

        if ($count === 0) {
            throw PzCurrInvalidAmountException::forValue('[]');
        }

        $totalRatios = '0';
        foreach ($ratioStrings as $r) {
            $totalRatios = bcadd($totalRatios, $r, self::HIGH_SCALE);
        }

        // Soma zero (ou ratios que se cancelam) impede distribuição.
        if (bccomp($totalRatios, '0', self::HIGH_SCALE) === 0) {
            throw PzCurrDivisionByZeroException::zeroRatios();
        }

        // Trabalha com valor absoluto; restaura sinal no final.
        $isNegative = bccomp($amount, '0', $scale) < 0;
        $absAmount  = $isNegative ? ltrim($amount, '-') : $amount;

        // Converte para unidades menores (string inteira).
        $multiplier = bcpow('10', (string) $scale);
        $totalMinor = bcmul($absAmount, $multiplier, 0);

        // Para cada ratio: floor(totalMinor × ratio / totalRatios).
        $floors = [];
        $fracs  = [];

        for ($i = 0; $i < $count; $i++) {
            $product    = bcmul($totalMinor, $ratioStrings[$i], self::HIGH_SCALE);
            $floor      = bcdiv($product, $totalRatios, 0); // truncamento == floor para positivos
            $floors[$i] = $floor;
            // Numerador fracionário: product − floor × totalRatios.
            $fracs[$i]  = bcsub($product, bcmul($floor, $totalRatios, self::HIGH_SCALE), self::HIGH_SCALE);
        }

        $sumFloors = '0';
        foreach ($floors as $f) {
            $sumFloors = bcadd($sumFloors, $f, 0);
        }

        $remainder = (int) bcsub($totalMinor, $sumFloors, 0);

        // Ordena índices por resto fracionário decrescente; empates mantêm ordem original.
        $indices = range(0, $count - 1);
        usort($indices, static function (int $a, int $b) use ($fracs, $ratioStrings): int {
            $cmp = bccomp($fracs[$b], $fracs[$a], self::HIGH_SCALE);
            if ($cmp !== 0) {
                return $cmp;
            }
            // Desempate: ratio maior recebe a unidade extra.
            return bccomp($ratioStrings[$b], $ratioStrings[$a], self::HIGH_SCALE);
        });

        // Aloca uma unidade menor extra às $remainder primeiras partes.
        $bonus = array_fill(0, $count, '0');
        for ($i = 0; $i < $remainder; $i++) {
            $bonus[$indices[$i]] = '1';
        }

        // Monta resultado: converte unidades menores de volta e restaura sinal.
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $minor   = bcadd($floors[$i], $bonus[$i], 0);
            $decimal = bcdiv($minor, $multiplier, $scale);
            // Evita '-0.00' / '-0' quando a parte é exatamente zero.
            $result[$keys[$i]] = ($isNegative && bccomp($decimal, '0', $scale) !== 0)
                ? '-' . $decimal
                : $decimal;
        }

        return $result;
    }

    /**
     * Divide $amount em $parts parcelas iguais pelo método do maior resto.
     *
     * @return array<int, string>
     */
    public static function split(string $amount, int $parts, int $scale): array
    {
        if ($parts < 1) {
            throw PzCurrInvalidAmountException::forValue((string) $parts);
        }

        $ratios = array_fill(0, $parts, 1);

        /** @var array<int, string> */
        return self::allocate($amount, $ratios, $scale);
    }

    /** Converte ratio para string decimal segura para bc*, sem aritmética float. */
    private static function ratioToString(int|float|string $ratio): string
    {
        if (is_float($ratio)) {
            $formatted = number_format($ratio, 14, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return (string) $ratio;
    }
}
