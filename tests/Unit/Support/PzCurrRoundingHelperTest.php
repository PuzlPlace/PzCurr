<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzCurr\Enum\PzCurrRoundingModeEnum;
use Puzl\PzCurr\Exception\PzCurrRoundingNecessaryException;
use Puzl\PzCurr\Support\PzCurrRoundingHelper;

final class PzCurrRoundingHelperTest extends TestCase
{
    // -----------------------------------------------------------------------
    // HALF_UP
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function halfUpProvider(): array
    {
        return [
            'positive half rounds up'          => ['2.345',  2, '2.35'],
            'positive just below half'         => ['2.344',  2, '2.34'],
            'positive above half'              => ['2.346',  2, '2.35'],
            'negative half rounds away zero'   => ['-2.345', 2, '-2.35'],
            'negative below half truncates'    => ['-2.344', 2, '-2.34'],
            'positive .5 scale 0'              => ['2.5',    0, '3'],
            'negative .5 scale 0'              => ['-2.5',   0, '-3'],
            'already at scale'                 => ['2.34',   2, '2.34'],
            'no decimal'                       => ['2',      2, '2'],
            'excess zeros do not change value' => ['2.340',  2, '2.34'],
            'large scale'                      => ['1.12345678905', 10, '1.1234567891'],
        ];
    }

    #[DataProvider('halfUpProvider')]
    public function test_half_up(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::HALF_UP));
    }

    // -----------------------------------------------------------------------
    // HALF_DOWN
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function halfDownProvider(): array
    {
        return [
            'positive half rounds toward zero'     => ['2.345',  2, '2.34'],
            'positive above half rounds up'        => ['2.346',  2, '2.35'],
            'positive below half truncates'        => ['2.344',  2, '2.34'],
            'negative half rounds toward zero'     => ['-2.345', 2, '-2.34'],
            'negative above half rounds away zero' => ['-2.346', 2, '-2.35'],
            'positive .5 scale 0'                  => ['2.5',    0, '2'],
            'negative .5 scale 0'                  => ['-2.5',   0, '-2'],
            'above half .51'                       => ['2.351',  2, '2.35'],
        ];
    }

    #[DataProvider('halfDownProvider')]
    public function test_half_down(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::HALF_DOWN));
    }

    // -----------------------------------------------------------------------
    // HALF_EVEN (banker's rounding)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function halfEvenProvider(): array
    {
        return [
            'half even digit 4 (even) truncates'   => ['2.345',  2, '2.34'],
            'half even digit 5 (odd) rounds up'    => ['2.355',  2, '2.36'],
            'half even digit 6 (even) truncates'   => ['2.365',  2, '2.36'],
            'half even digit 3 (odd) rounds up'    => ['2.335',  2, '2.34'],
            'above half always rounds up'           => ['2.346',  2, '2.35'],
            'below half always truncates'           => ['2.344',  2, '2.34'],
            'negative half digit 4 (even) truncate'=> ['-2.345', 2, '-2.34'],
            'negative half digit 5 (odd) rounds'   => ['-2.355', 2, '-2.36'],
            'scale 0 even integer truncates'       => ['2.5',    0, '2'],
            'scale 0 odd integer rounds up'        => ['3.5',    0, '4'],
            'scale 0 negative odd rounds away'     => ['-3.5',   0, '-4'],
            'scale 0 negative even truncates'      => ['-2.5',   0, '-2'],
            'half even trailing non-zero'          => ['2.3451', 2, '2.35'],
        ];
    }

    #[DataProvider('halfEvenProvider')]
    public function test_half_even(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::HALF_EVEN));
    }

    // -----------------------------------------------------------------------
    // UP (para longe de zero)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function upProvider(): array
    {
        return [
            'positive any excess rounds up'    => ['2.341',  2, '2.35'],
            'positive tiny excess rounds up'   => ['2.340001', 2, '2.35'],
            'negative any excess rounds away'  => ['-2.341', 2, '-2.35'],
            'no excess stays same'             => ['2.34',   2, '2.34'],
            'scale 0 any decimal rounds up'    => ['2.1',    0, '3'],
            'scale 0 negative rounds away'     => ['-2.1',   0, '-3'],
            'excess zeros no change'           => ['2.340',  2, '2.34'],
        ];
    }

    #[DataProvider('upProvider')]
    public function test_up(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::UP));
    }

    // -----------------------------------------------------------------------
    // DOWN (em direção a zero / trunca)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function downProvider(): array
    {
        return [
            'positive truncates'             => ['2.349',  2, '2.34'],
            'positive large excess truncates'=> ['2.399',  2, '2.39'],
            'negative truncates toward zero' => ['-2.349', 2, '-2.34'],
            'no excess stays same'           => ['2.34',   2, '2.34'],
            'scale 0 drops decimal'          => ['2.9',    0, '2'],
            'scale 0 negative truncates'     => ['-2.9',   0, '-2'],
        ];
    }

    #[DataProvider('downProvider')]
    public function test_down(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::DOWN));
    }

    // -----------------------------------------------------------------------
    // CEILING (em direção a +∞)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function ceilingProvider(): array
    {
        return [
            'positive excess rounds toward +inf'   => ['2.341',  2, '2.35'],
            'negative excess truncates toward +inf'=> ['-2.341', 2, '-2.34'],
            'no excess stays same'                 => ['2.34',   2, '2.34'],
            'scale 0 positive'                     => ['2.1',    0, '3'],
            'scale 0 negative'                     => ['-2.9',   0, '-2'],
            'excess zeros no change'               => ['2.340',  2, '2.34'],
        ];
    }

    #[DataProvider('ceilingProvider')]
    public function test_ceiling(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::CEILING));
    }

    // -----------------------------------------------------------------------
    // FLOOR (em direção a −∞)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function floorProvider(): array
    {
        return [
            'positive excess truncates'             => ['2.349',  2, '2.34'],
            'negative excess rounds toward -inf'    => ['-2.341', 2, '-2.35'],
            'no excess stays same'                  => ['2.34',   2, '2.34'],
            'scale 0 positive truncates'            => ['2.9',    0, '2'],
            'scale 0 negative rounds toward -inf'   => ['-2.1',   0, '-3'],
            'excess zeros no change'                => ['2.340',  2, '2.34'],
        ];
    }

    #[DataProvider('floorProvider')]
    public function test_floor(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, PzCurrRoundingHelper::round($amount, $scale, PzCurrRoundingModeEnum::FLOOR));
    }

    // -----------------------------------------------------------------------
    // UNNECESSARY
    // -----------------------------------------------------------------------

    public function test_unnecessary_throws_when_rounding_required(): void
    {
        $this->expectException(PzCurrRoundingNecessaryException::class);
        PzCurrRoundingHelper::round('2.345', 2, PzCurrRoundingModeEnum::UNNECESSARY);
    }

    public function test_unnecessary_throws_when_guide_digit_nonzero(): void
    {
        $this->expectException(PzCurrRoundingNecessaryException::class);
        PzCurrRoundingHelper::round('2.301', 2, PzCurrRoundingModeEnum::UNNECESSARY);
    }

    public function test_unnecessary_throws_contains_amount_and_scale(): void
    {
        try {
            PzCurrRoundingHelper::round('2.345', 2, PzCurrRoundingModeEnum::UNNECESSARY);
            $this->fail('Expected exception not thrown');
        } catch (PzCurrRoundingNecessaryException $e) {
            $this->assertStringContainsString('2.345', $e->getMessage());
            $this->assertStringContainsString('2', $e->getMessage());
        }
    }

    public function test_unnecessary_returns_when_no_excess(): void
    {
        $result = PzCurrRoundingHelper::round('2.30', 2, PzCurrRoundingModeEnum::UNNECESSARY);
        $this->assertSame('2.30', $result);
    }

    public function test_unnecessary_returns_when_excess_all_zeros(): void
    {
        $result = PzCurrRoundingHelper::round('2.300', 2, PzCurrRoundingModeEnum::UNNECESSARY);
        $this->assertSame('2.30', $result);
    }

    public function test_unnecessary_returns_when_at_exact_scale(): void
    {
        $result = PzCurrRoundingHelper::round('2.34', 2, PzCurrRoundingModeEnum::UNNECESSARY);
        $this->assertSame('2.34', $result);
    }

    // -----------------------------------------------------------------------
    // Casos limite
    // -----------------------------------------------------------------------

    public function test_zero_scale_with_no_decimal(): void
    {
        $result = PzCurrRoundingHelper::round('100', 0, PzCurrRoundingModeEnum::HALF_UP);
        $this->assertSame('100', $result);
    }

    public function test_zero_value_returns_unchanged(): void
    {
        $result = PzCurrRoundingHelper::round('0.00', 2, PzCurrRoundingModeEnum::HALF_UP);
        $this->assertSame('0.00', $result);
    }

    public function test_negative_zero_unnecessary_no_excess(): void
    {
        $result = PzCurrRoundingHelper::round('-2.34', 2, PzCurrRoundingModeEnum::UNNECESSARY);
        $this->assertSame('-2.34', $result);
    }

    public function test_ceiling_negative_exact_half(): void
    {
        // CEILING: em direção a +∞; -2.345 → -2.34 (trunca)
        $result = PzCurrRoundingHelper::round('-2.345', 2, PzCurrRoundingModeEnum::CEILING);
        $this->assertSame('-2.34', $result);
    }

    public function test_floor_positive_exact_half(): void
    {
        // FLOOR: em direção a −∞; 2.345 → 2.34 (trunca)
        $result = PzCurrRoundingHelper::round('2.345', 2, PzCurrRoundingModeEnum::FLOOR);
        $this->assertSame('2.34', $result);
    }

    public function test_floor_negative_exact_half(): void
    {
        // FLOOR: em direção a −∞; -2.345 → -2.35
        $result = PzCurrRoundingHelper::round('-2.345', 2, PzCurrRoundingModeEnum::FLOOR);
        $this->assertSame('-2.35', $result);
    }
}
