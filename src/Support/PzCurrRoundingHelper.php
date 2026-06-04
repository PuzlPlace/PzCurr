<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Support;

use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrRoundingNecessaryException;

/**
 * Rounding helper for decimal string values.
 *
 * Strategy: all logic operates on the string representation of the number.
 * No casts to float are performed at any stage.
 *
 * Algorithm overview:
 *  1. Parse sign, integer part, and decimal part from the input string.
 *  2. If the decimal part is already within the target scale, return as-is.
 *  3. Extract the "guide digit" — the first digit beyond the target scale —
 *     and check whether any subsequent digit is non-zero.
 *  4. Build the "truncated" value (toward zero) and, when needed, the
 *     "rounded-up" value (away from zero) via bcadd/bcsub of 1 ULP
 *     (unit in the last place, e.g. 0.01 for scale=2).
 *  5. Apply the rounding decision based on the mode, the guide digit,
 *     the sign, and (for HALF_EVEN) the parity of the last kept digit.
 *
 * Compatible with PHP 8.1+ (does not use bcround, available only in PHP 8.4+).
 */
final class PzCurrRoundingHelper
{
    /**
     * Rounds the decimal string $amount to $scale decimal places using $mode.
     *
     * @throws PzCurrRoundingNecessaryException when $mode is UNNECESSARY and rounding would be required.
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

        // Already within target scale — no rounding required.
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

        // No non-zero excess beyond the scale; truncation is exact.
        if (!$hasNonZeroExcess) {
            return $truncated;
        }

        // Unit in the last place for the target scale.
        $ulp    = $scale > 0 ? '0.' . str_repeat('0', $scale - 1) . '1' : '1';
        $addend = $negative ? '-' . $ulp : $ulp;

        // Value rounded away from zero by exactly 1 ULP.
        $roundedUp = bcadd($truncated, $addend, $scale);

        return match ($mode) {
            PzCurrRoundingModeEnum::DOWN => $truncated,

            PzCurrRoundingModeEnum::UP => $roundedUp,

            // CEILING: toward +∞. Positive values round up; negative values truncate.
            PzCurrRoundingModeEnum::CEILING => $negative ? $truncated : $roundedUp,

            // FLOOR: toward −∞. Negative values round away from zero; positive truncate.
            PzCurrRoundingModeEnum::FLOOR => $negative ? $roundedUp : $truncated,

            // HALF_UP: ties go away from zero.
            PzCurrRoundingModeEnum::HALF_UP => $guideDigit >= 5 ? $roundedUp : $truncated,

            // HALF_DOWN: ties go toward zero.
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

            // UNNECESSARY is fully handled above; this branch is unreachable.
            PzCurrRoundingModeEnum::UNNECESSARY => $truncated,
        };
    }

    /**
     * Returns the value truncated toward zero at the given scale.
     */
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

    /**
     * HALF_EVEN (banker's rounding): when exactly halfway, round to even last digit.
     */
    private static function applyHalfEven(
        string $truncated,
        string $roundedUp,
        int $guideDigit,
        string $remainder,
        string $intPart,
        string $decPart,
        int $scale,
    ): string {
        // More than half: always round away from zero.
        if ($guideDigit > 5 || ($guideDigit === 5 && ltrim($remainder, '0') !== '')) {
            return $roundedUp;
        }

        // Less than half: always truncate.
        if ($guideDigit < 5) {
            return $truncated;
        }

        // Exactly half: round to even (last kept digit must be even).
        if ($scale === 0) {
            $lastKeptDigit = (int) (strlen($intPart) > 0 ? $intPart[-1] : '0');
        } else {
            $kept          = substr($decPart, 0, $scale);
            $lastKeptDigit = (int) (strlen($kept) > 0 ? $kept[-1] : '0');
        }

        return ($lastKeptDigit % 2 === 0) ? $truncated : $roundedUp;
    }
}
