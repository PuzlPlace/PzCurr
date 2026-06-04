<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Support;

use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrRoundingNecessaryException;

/**
 * Helper de arredondamento para strings decimais.
 *
 * Toda a lógica opera sobre a representação string, sem cast para float.
 * Compatível com PHP 8.1+ (não usa bcround, disponível apenas no PHP 8.4+).
 */
final class PzCurrRoundingHelper
{
    /**
     * Arredonda a string decimal $amount para $scale casas usando $mode.
     *
     * @throws PzCurrRoundingNecessaryException quando $mode é UNNECESSARY e arredondamento seria necessário.
     */
    public static function round(
        string $amount,
        int $scale,
        PzCurrRoundingModeEnum $mode,
    ): string {
        $negative = str_starts_with($amount, '-');
        $abs = $negative ? substr($amount, 1) : $amount;

        if (str_contains($abs, '.')) {
            [$intPart, $decPart] = explode('.', $abs, 2);
        } else {
            $intPart = $abs;
            $decPart = '';
        }

        // Já cabe na escala alvo — sem arredondamento.
        if (strlen($decPart) <= $scale) {
            return $amount;
        }

        $guideDigit = (int) $decPart[$scale];
        $remainder  = substr($decPart, $scale + 1);
        $hasNonZeroExcess = $guideDigit !== 0 || ltrim($remainder, '0') !== '';

        $truncated = self::buildTruncated($negative, $intPart, $decPart, $scale);

        if ($mode === PzCurrRoundingModeEnum::UNNECESSARY) {
            if ($hasNonZeroExcess) {
                throw PzCurrRoundingNecessaryException::forScale($amount, $scale);
            }
            return $truncated;
        }

        // Sem excesso além da escala; truncamento é exato.
        if (!$hasNonZeroExcess) {
            return $truncated;
        }

        // Unidade na última casa da escala alvo (ULP).
        $ulp    = $scale > 0 ? '0.' . str_repeat('0', $scale - 1) . '1' : '1';
        $addend = $negative ? '-' . $ulp : $ulp;

        // Valor arredondado uma ULP para longe de zero.
        $roundedUp = bcadd($truncated, $addend, $scale);

        return match ($mode) {
            PzCurrRoundingModeEnum::DOWN => $truncated,

            PzCurrRoundingModeEnum::UP => $roundedUp,

            // CEILING: em direção a +∞. Positivos sobem; negativos truncam.
            PzCurrRoundingModeEnum::CEILING => $negative ? $truncated : $roundedUp,

            // FLOOR: em direção a −∞. Negativos descem; positivos truncam.
            PzCurrRoundingModeEnum::FLOOR => $negative ? $roundedUp : $truncated,

            // HALF_UP: empates vão para longe de zero.
            PzCurrRoundingModeEnum::HALF_UP => $guideDigit >= 5 ? $roundedUp : $truncated,

            // HALF_DOWN: empates vão em direção a zero.
            PzCurrRoundingModeEnum::HALF_DOWN => (
                $guideDigit > 5 || ($guideDigit === 5 && ltrim($remainder, '0') !== '')
            ) ? $roundedUp : $truncated,

            PzCurrRoundingModeEnum::HALF_EVEN => self::applyHalfEven(
                $truncated,
                $roundedUp,
                $guideDigit,
                $remainder,
                $intPart,
                $decPart,
                $scale,
            ),

            // UNNECESSARY já tratado acima; ramo inalcançável.
            PzCurrRoundingModeEnum::UNNECESSARY => $truncated,
        };
    }

    /** Retorna o valor truncado em direção a zero na escala informada. */
    private static function buildTruncated(
        bool $negative,
        string $intPart,
        string $decPart,
        int $scale,
    ): string {
        $sign = $negative ? '-' : '';

        if ($scale === 0) {
            return $sign . $intPart;
        }

        $kept = substr($decPart, 0, $scale);

        if (strlen($kept) < $scale) {
            $kept = str_pad($kept, $scale, '0');
        }

        return $sign . $intPart . '.' . $kept;
    }

    /** HALF_EVEN (banker's rounding): no meio exato, arredonda para dígito par. */
    private static function applyHalfEven(
        string $truncated,
        string $roundedUp,
        int $guideDigit,
        string $remainder,
        string $intPart,
        string $decPart,
        int $scale,
    ): string {
        // Mais da metade: sempre arredonda para longe de zero.
        if ($guideDigit > 5 || ($guideDigit === 5 && ltrim($remainder, '0') !== '')) {
            return $roundedUp;
        }

        // Menos da metade: sempre trunca.
        if ($guideDigit < 5) {
            return $truncated;
        }

        // Exatamente na metade: arredonda para par (último dígito mantido deve ser par).
        if ($scale === 0) {
            $lastKeptDigit = (int) (strlen($intPart) > 0 ? $intPart[-1] : '0');
        } else {
            $kept          = substr($decPart, 0, $scale);
            $lastKeptDigit = (int) (strlen($kept) > 0 ? $kept[-1] : '0');
        }

        return ($lastKeptDigit % 2 === 0) ? $truncated : $roundedUp;
    }
}
