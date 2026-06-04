<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Support;

use Puzl\PzCurr\Exception\PzCurrDivisionByZeroException;
use Puzl\PzCurr\Exception\PzCurrInvalidAmountException;

/**
 * Largest-remainder algorithm for distributing a monetary amount across ratios.
 *
 * Algorithm overview:
 *  1. Converts amount to minor units (integer string via bc*) to avoid decimal arithmetic.
 *  2. For each ratio, computes floor(totalMinor × ratio / totalRatios) using integer bc*.
 *  3. Identifies fractional remainders: frac_i = totalMinor×ratio_i − floor_i×totalRatios.
 *  4. Distributes leftover minor units (one at a time) to parts with the largest fractional
 *     remainder, using stable ordering for deterministic results when fractions tie.
 *  5. Converts each minor-unit result back to the target decimal scale via bcdiv.
 *
 * Guarantees:
 *  - Σ parts == amount (exact conservation), regardless of scale.
 *  - Works with negative amounts (sign is stripped, applied at end).
 *  - Works with scale 0 (e.g. JPY).
 *  - Zero float arithmetic: float ratios are serialised to strings via number_format before
 *    any bc* call.
 *  - Result keys mirror the input $ratios keys (associative preservation).
 */
final class PzCurrAllocator
{
    /** Decimal places used for intermediate bc* calculations to avoid truncation errors. */
    private const HIGH_SCALE = 40;

    /**
     * Distributes $amount among the given $ratios using the largest-remainder method.
     *
     * @param string                                 $amount  Decimal string (e.g. '10.00').
     * @param array<int|string, int|float|string>    $ratios  Relative weights; keys are preserved.
     * @param int                                    $scale   Decimal places (e.g. 2 for BRL).
     *
     * @return array<int|string, string> Parts in the target scale; Σ parts == $amount.
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

        // A zero (or fully cancelling) ratio set cannot distribute the amount.
        if (bccomp($totalRatios, '0', self::HIGH_SCALE) === 0) {
            throw PzCurrDivisionByZeroException::zeroRatios();
        }

        // Work with the absolute value; restore sign at the end.
        $isNegative = bccomp($amount, '0', $scale) < 0;
        $absAmount  = $isNegative ? ltrim($amount, '-') : $amount;

        // Convert to minor units (integer string).
        $multiplier = bcpow('10', (string) $scale);
        $totalMinor = bcmul($absAmount, $multiplier, 0);

        // For each ratio: floor(totalMinor × ratio / totalRatios).
        $floors = [];
        $fracs  = [];

        for ($i = 0; $i < $count; $i++) {
            $product    = bcmul($totalMinor, $ratioStrings[$i], self::HIGH_SCALE);
            $floor      = bcdiv($product, $totalRatios, 0); // truncation == floor for positives
            $floors[$i] = $floor;
            // Fractional numerator: product − floor × totalRatios (in [0, totalRatios)).
            $fracs[$i]  = bcsub($product, bcmul($floor, $totalRatios, self::HIGH_SCALE), self::HIGH_SCALE);
        }

        $sumFloors = '0';
        foreach ($floors as $f) {
            $sumFloors = bcadd($sumFloors, $f, 0);
        }

        $remainder = (int) bcsub($totalMinor, $sumFloors, 0);

        // Sort part indices by fractional remainder descending; ties keep original (stable) order.
        $indices = range(0, $count - 1);
        usort($indices, static function (int $a, int $b) use ($fracs, $ratioStrings): int {
            $cmp = bccomp($fracs[$b], $fracs[$a], self::HIGH_SCALE);
            if ($cmp !== 0) {
                return $cmp;
            }
            // Secondary tie-breaker: larger ratio wins the extra unit, making the result
            // independent of the order in which equal-frac ratios appear in the input.
            return bccomp($ratioStrings[$b], $ratioStrings[$a], self::HIGH_SCALE);
        });

        // Allocate one extra minor unit to each of the top-$remainder parts.
        $bonus = array_fill(0, $count, '0');
        for ($i = 0; $i < $remainder; $i++) {
            $bonus[$indices[$i]] = '1';
        }

        // Build result: convert minor units back to decimal and restore sign.
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $minor   = bcadd($floors[$i], $bonus[$i], 0);
            $decimal = bcdiv($minor, $multiplier, $scale);
            // Avoid '-0.00' / '-0' when a part is exactly zero.
            $result[$keys[$i]] = ($isNegative && bccomp($decimal, '0', $scale) !== 0)
                ? '-' . $decimal
                : $decimal;
        }

        return $result;
    }

    /**
     * Splits $amount into $parts equal shares using the largest-remainder method.
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

    /**
     * Converts a ratio value to a bc*-safe decimal string without using float arithmetic.
     *
     * Floats are serialised through number_format (14 decimal places) so that common
     * values like 0.5, 0.25, 7.5 map to clean strings. int and string ratios are cast
     * directly.
     */
    private static function ratioToString(int|float|string $ratio): string
    {
        if (is_float($ratio)) {
            $formatted = number_format($ratio, 14, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return (string) $ratio;
    }
}
