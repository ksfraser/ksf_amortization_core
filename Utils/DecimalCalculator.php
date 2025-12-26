<?php

namespace Ksfraser\Amortizations\Utils;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * DecimalCalculator - Precise financial arithmetic utility
 *
 * Wraps brick/math library to provide arbitrary precision decimal calculations
 * for loan amortization. Eliminates floating-point errors that accumulate over
 * many payment periods.
 *
 * Key benefits:
 * - Arbitrary precision (no rounding errors)
 * - All calculations use string-based decimals
 * - Final output to 2 decimal places (cents) with consistent rounding
 * - Industry-standard for financial calculations
 *
 * ### Usage Examples
 *
 * Single operations:
 * ```php
 * $calc = new DecimalCalculator();
 * $monthly_rate = $calc->divide(5.0, 12);           // 5% / 12
 * $power = $calc->power('1.004167', 360);            // (1.004167)^360
 * $result = $calc->multiply($principal, $monthly_rate); // P * r
 * ```
 *
 * Complex formula (standard amortization):
 * ```php
 * $principal = 10000;
 * $rate = 5;
 * $months = 360;
 *
 * $r = $calc->divide($rate, 12, 6);                    // Monthly rate (6 decimals)
 * $r_plus_1 = $calc->add(1, $r);                        // 1 + r
 * $power = $calc->power($r_plus_1, $months);            // (1+r)^n
 * $numerator = $calc->multiply($principal, $r);         // P * r
 * $numerator = $calc->multiply($numerator, $power);     // P * r * (1+r)^n
 * $denominator = $calc->subtract($power, 1);            // (1+r)^n - 1
 * $payment = $calc->divide($numerator, $denominator, 2); // Final payment
 * ```
 *
 * @package Ksfraser\Amortizations\Utils
 * @since 2.0
 */
class DecimalCalculator
{
    /**
     * @var int Precision for intermediate calculations (prevents rounding issues)
     */
    private $internalPrecision = 10;

    /**
     * @var int Final output precision (2 decimal places = cents)
     */
    private $outputPrecision = 2;

    /**
     * Add two decimal numbers
     *
     * @param float|int|string $a First number
     * @param float|int|string $b Second number
     * @param int|null $precision Output precision (default: internal)
     *
     * @return string Result as decimal string
     */
    public function add($a, $b, ?int $precision = null): string
    {
        $precision = $precision ?? $this->internalPrecision;
        $result = BigDecimal::of($a)
            ->plus($b)
            ->toScale($precision, RoundingMode::HALF_UP);
        return (string)$result;
    }

    /**
     * Subtract two decimal numbers
     *
     * @param float|int|string $a First number
     * @param float|int|string $b Second number
     * @param int|null $precision Output precision (default: internal)
     *
     * @return string Result as decimal string
     */
    public function subtract($a, $b, ?int $precision = null): string
    {
        $precision = $precision ?? $this->internalPrecision;
        $result = BigDecimal::of($a)
            ->minus($b)
            ->toScale($precision, RoundingMode::HALF_UP);
        return (string)$result;
    }

    /**
     * Multiply two decimal numbers
     *
     * @param float|int|string $a First number
     * @param float|int|string $b Second number
     * @param int|null $precision Output precision (default: internal)
     *
     * @return string Result as decimal string
     */
    public function multiply($a, $b, ?int $precision = null): string
    {
        $precision = $precision ?? $this->internalPrecision;
        $result = BigDecimal::of($a)
            ->multipliedBy($b)
            ->toScale($precision, RoundingMode::HALF_UP);
        return (string)$result;
    }

    /**
     * Divide two decimal numbers
     *
     * @param float|int|string $a Dividend
     * @param float|int|string $b Divisor
     * @param int|null $precision Output precision (default: internal)
     *
     * @return string Result as decimal string
     * @throws \InvalidArgumentException If divisor is zero
     */
    public function divide($a, $b, ?int $precision = null): string
    {
        if (BigDecimal::of($b)->isEqualTo(0)) {
            throw new \InvalidArgumentException('Division by zero');
        }
        $precision = $precision ?? $this->internalPrecision;
        $result = BigDecimal::of($a)
            ->dividedBy($b, $precision, RoundingMode::HALF_UP);
        return (string)$result;
    }

    /**
     * Raise a number to a power
     *
     * @param float|int|string $base Base number
     * @param int $exponent Exponent (integer only)
     * @param int|null $precision Output precision (default: internal)
     *
     * @return string Result as decimal string
     */
    public function power($base, int $exponent, ?int $precision = null): string
    {
        $precision = $precision ?? $this->internalPrecision;
        $result = BigDecimal::of($base)
            ->power($exponent)
            ->toScale($precision, RoundingMode::HALF_UP);
        return (string)$result;
    }

    /**
     * Round a number to specified decimal places
     *
     * @param float|int|string $value Value to round
     * @param int $decimals Number of decimal places
     *
     * @return string Rounded value as decimal string
     */
    public function round($value, int $decimals = 2): string
    {
        $result = BigDecimal::of($value)
            ->toScale($decimals, RoundingMode::HALF_UP);
        return (string)$result;
    }

    /**
     * Convert to float for final output
     *
     * @param string $value Decimal string value
     * @param int $decimals Number of decimal places for output
     *
     * @return float Final float value
     */
    public function toFloat(string $value, int $decimals = 2): float
    {
        $rounded = $this->round($value, $decimals);
        return (float)$rounded;
    }

    /**
     * Convert to float (convenience method)
     *
     * @param float|int|string $value Value to convert
     * @param int $decimals Number of decimal places
     *
     * @return float
     */
    public function asFloat($value, int $decimals = 2): float
    {
        if (is_float($value) || is_int($value)) {
            $value = (string)$value;
        }
        return $this->toFloat($value, $decimals);
    }

    /**
     * Maximum of two or more numbers
     *
     * @param float|int|string $a First number
     * @param float|int|string $b Second number
     * @param float|int|string ...$others Additional numbers
     *
     * @return string Maximum value as decimal string
     */
    public function max($a, $b, ...$others): string
    {
        $values = [$a, $b, ...$others];
        $max = BigDecimal::of($values[0]);

        foreach (array_slice($values, 1) as $value) {
            $bd = BigDecimal::of($value);
            if ($bd->isGreaterThan($max)) {
                $max = $bd;
            }
        }

        return (string)$max->toScale($this->internalPrecision, RoundingMode::HALF_UP);
    }

    /**
     * Minimum of two or more numbers
     *
     * @param float|int|string $a First number
     * @param float|int|string $b Second number
     * @param float|int|string ...$others Additional numbers
     *
     * @return string Minimum value as decimal string
     */
    public function min($a, $b, ...$others): string
    {
        $values = [$a, $b, ...$others];
        $min = BigDecimal::of($values[0]);

        foreach (array_slice($values, 1) as $value) {
            $bd = BigDecimal::of($value);
            if ($bd->isLessThan($min)) {
                $min = $bd;
            }
        }

        return (string)$min->toScale($this->internalPrecision, RoundingMode::HALF_UP);
    }

    /**
     * Absolute value
     *
     * @param float|int|string $value Value
     *
     * @return string Absolute value as decimal string
     */
    public function abs($value): string
    {
        $bd = BigDecimal::of($value);
        if ($bd->isNegative()) {
            $bd = $bd->negated();
        }
        return (string)$bd->toScale($this->internalPrecision, RoundingMode::HALF_UP);
    }

    /**
     * Check if value is zero
     *
     * @param float|int|string $value Value to check
     *
     * @return bool
     */
    public function isZero($value): bool
    {
        return BigDecimal::of($value)->isEqualTo(0);
    }

    /**
     * Compare two values
     *
     * @param float|int|string $a First value
     * @param float|int|string $b Second value
     *
     * @return int -1 if a < b, 0 if a == b, 1 if a > b
     */
    public function compare($a, $b): int
    {
        $a_bd = BigDecimal::of($a);
        $b_bd = BigDecimal::of($b);

        if ($a_bd->isLessThan($b_bd)) {
            return -1;
        } elseif ($a_bd->isGreaterThan($b_bd)) {
            return 1;
        }

        return 0;
    }

    /**
     * Set internal precision for intermediate calculations
     *
     * @param int $precision Decimal places for internal calculations
     *
     * @return void
     */
    public function setInternalPrecision(int $precision): void
    {
        if ($precision < 2) {
            throw new \InvalidArgumentException('Internal precision must be at least 2');
        }
        $this->internalPrecision = $precision;
    }

    /**
     * Set output precision (default: 2 for cents)
     *
     * @param int $precision Decimal places for final output
     *
     * @return void
     */
    public function setOutputPrecision(int $precision): void
    {
        if ($precision < 0) {
            throw new \InvalidArgumentException('Output precision must be non-negative');
        }
        $this->outputPrecision = $precision;
    }
}
