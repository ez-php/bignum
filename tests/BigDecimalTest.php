<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\BigNum\Backend\GmpBackend;
use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\BigNum\DivisionByZeroException;
use EzPhp\BigNum\RoundingMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * @covers \EzPhp\BigNum\BigDecimal
 * @requires extension gmp
 */
#[CoversClass(BigDecimal::class)]
#[UsesClass(BigInteger::class)]
#[UsesClass(GmpBackend::class)]
#[UsesClass(RoundingMode::class)]
#[UsesClass(DivisionByZeroException::class)]
#[RequiresPhpExtension('gmp')]
final class BigDecimalTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public function testOfInt(): void
    {
        self::assertSame('42', BigDecimal::of(42)->toString());
        self::assertSame(0, BigDecimal::of(42)->getScale());
    }

    public function testOfString(): void
    {
        self::assertSame('123.45', BigDecimal::of('123.45')->toString());
        self::assertSame(2, BigDecimal::of('123.45')->getScale());
    }

    public function testOfStringNoDecimals(): void
    {
        self::assertSame('100', BigDecimal::of('100')->toString());
        self::assertSame(0, BigDecimal::of('100')->getScale());
    }

    public function testOfNegative(): void
    {
        self::assertSame('-0.005', BigDecimal::of('-0.005')->toString());
        self::assertSame(3, BigDecimal::of('-0.005')->getScale());
    }

    public function testOfZero(): void
    {
        self::assertTrue(BigDecimal::of('0')->isZero());
        self::assertTrue(BigDecimal::of('0.000')->isZero());
        self::assertTrue(BigDecimal::of(0)->isZero());
    }

    public function testOfFloat(): void
    {
        $v = BigDecimal::of(1.5);
        self::assertSame('1.5', $v->toString());
    }

    public function testOfFloatNonFiniteThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::of(INF);
    }

    public function testOfInvalidStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::of('not-a-number');
    }

    public function testOfUnscaledValue(): void
    {
        $v = BigDecimal::ofUnscaledValue('12345', 2);
        self::assertSame('123.45', $v->toString());
        self::assertSame(2, $v->getScale());
        self::assertSame('12345', $v->getUnscaledValue());
    }

    public function testOfUnscaledValueNegativeScaleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::ofUnscaledValue('100', -1);
    }

    public function testOfUnscaledValueInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::ofUnscaledValue('1.5', 2);
    }

    public function testZeroFactory(): void
    {
        self::assertTrue(BigDecimal::zero()->isZero());
        self::assertSame(0, BigDecimal::zero()->getScale());
    }

    public function testOneFactory(): void
    {
        self::assertSame('1', BigDecimal::one()->toString());
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function testGetScale(): void
    {
        self::assertSame(3, BigDecimal::of('1.500')->getScale());
    }

    public function testGetUnscaledValue(): void
    {
        self::assertSame('1500', BigDecimal::of('1.500')->getUnscaledValue());
        self::assertSame('-5', BigDecimal::of('-0.005')->getUnscaledValue());
    }

    // -------------------------------------------------------------------------
    // Arithmetic
    // -------------------------------------------------------------------------

    public function testAdd(): void
    {
        self::assertSame('3.0', BigDecimal::of('1.5')->add('1.5')->toString());
        self::assertSame('0.30', BigDecimal::of('0.10')->add('0.20')->toString());
    }

    public function testAddScaleAlignment(): void
    {
        // Result scale = max(1, 2) = 2
        $result = BigDecimal::of('1.5')->add('0.05');
        self::assertSame('1.55', $result->toString());
        self::assertSame(2, $result->getScale());
    }

    public function testAddBigInteger(): void
    {
        $result = BigDecimal::of('1.5')->add(BigInteger::of(2));
        self::assertSame('3.5', $result->toString());
    }

    public function testSubtract(): void
    {
        self::assertSame('1.0', BigDecimal::of('2.5')->subtract('1.5')->toString());
        self::assertSame('-1.5', BigDecimal::of('1.0')->subtract('2.5')->toString());
    }

    public function testMultiply(): void
    {
        self::assertSame('6.25', BigDecimal::of('2.5')->multiply('2.5')->toString());
        // Scale = 0 + 2 = 2, so result is "0.00"
        self::assertSame('0.00', BigDecimal::of('0')->multiply('999.99')->toString());
    }

    public function testMultiplyScaleIsSum(): void
    {
        $result = BigDecimal::of('1.5')->multiply('2.00');
        self::assertSame('3.000', $result->toString());
        self::assertSame(3, $result->getScale());
    }

    public function testDivide(): void
    {
        // Integer division: 10 / 3 = 3 (scale 0)
        self::assertSame('3', BigDecimal::of('10')->divide('3')->toString());
    }

    public function testDivideByZero(): void
    {
        $this->expectException(DivisionByZeroException::class);
        BigDecimal::of('1')->divide('0');
    }

    public function testDividedBy(): void
    {
        self::assertSame('3.33', BigDecimal::of('10')->dividedBy('3', 2)->toString());
        self::assertSame('3.333333', BigDecimal::of('10')->dividedBy('3', 6)->toString());
    }

    public function testDividedByExact(): void
    {
        self::assertSame('2.5', BigDecimal::of('5')->dividedBy('2', 1)->toString());
    }

    public function testDividedByNegativeScaleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::of('10')->dividedBy('3', -1);
    }

    public function testMod(): void
    {
        self::assertSame('1', BigDecimal::of('10')->mod('3')->toString());
        self::assertSame('-1', BigDecimal::of('-10')->mod('3')->toString());
    }

    public function testModByZero(): void
    {
        $this->expectException(DivisionByZeroException::class);
        BigDecimal::of('10')->mod('0');
    }

    public function testPow(): void
    {
        self::assertSame('8', BigDecimal::of('2')->pow(3)->toString());
        self::assertSame('1', BigDecimal::of('99')->pow(0)->toString());
    }

    public function testPowScaleIsProduct(): void
    {
        $result = BigDecimal::of('1.5')->pow(2);
        self::assertSame('2.25', $result->toString());
        self::assertSame(2, $result->getScale()); // 1 * 2
    }

    public function testPowNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::of('2')->pow(-1);
    }

    public function testAbs(): void
    {
        self::assertSame('1.5', BigDecimal::of('-1.5')->abs()->toString());
        self::assertSame('1.5', BigDecimal::of('1.5')->abs()->toString());
        self::assertSame('0', BigDecimal::of('0')->abs()->toString());
    }

    public function testNegate(): void
    {
        self::assertSame('-1.5', BigDecimal::of('1.5')->negate()->toString());
        self::assertSame('1.5', BigDecimal::of('-1.5')->negate()->toString());
        self::assertSame('0', BigDecimal::of('0')->negate()->toString());
    }

    // -------------------------------------------------------------------------
    // Scale and rounding
    // -------------------------------------------------------------------------

    public function testToScaleExtend(): void
    {
        $result = BigDecimal::of('1.5')->toScale(3);
        self::assertSame('1.500', $result->toString());
        self::assertSame(3, $result->getScale());
    }

    public function testToScaleReduce(): void
    {
        self::assertSame('1.23', BigDecimal::of('1.234')->toScale(2, RoundingMode::DOWN)->toString());
        self::assertSame('1.24', BigDecimal::of('1.235')->toScale(2, RoundingMode::HALF_UP)->toString());
    }

    public function testToScaleNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::of('1.5')->toScale(-1);
    }

    public function testRoundIsAliasForToScale(): void
    {
        self::assertSame('1.24', BigDecimal::of('1.235')->round(2, RoundingMode::HALF_UP)->toString());
        self::assertSame('1.23', BigDecimal::of('1.235')->round(2, RoundingMode::HALF_DOWN)->toString());
    }

    /**
     * @param string       $value
     * @param int          $scale
     * @param RoundingMode $mode
     * @param string       $expected
     */
    #[DataProvider('roundingProvider')]
    public function testRoundingModes(string $value, int $scale, RoundingMode $mode, string $expected): void
    {
        self::assertSame($expected, BigDecimal::of($value)->toScale($scale, $mode)->toString());
    }

    /**
     * @return array<string, array{string, int, RoundingMode, string}>
     */
    public static function roundingProvider(): array
    {
        return [
            'UP positive' => ['1.234', 2, RoundingMode::UP,        '1.24'],
            'UP negative' => ['-1.234', 2, RoundingMode::UP,       '-1.24'],
            'DOWN positive' => ['1.239', 2, RoundingMode::DOWN,      '1.23'],
            'DOWN negative' => ['-1.239', 2, RoundingMode::DOWN,     '-1.23'],
            'CEILING positive' => ['1.231', 2, RoundingMode::CEILING,   '1.24'],
            'CEILING negative' => ['-1.239', 2, RoundingMode::CEILING,  '-1.23'],
            'FLOOR positive' => ['1.239', 2, RoundingMode::FLOOR,     '1.23'],
            'FLOOR negative' => ['-1.231', 2, RoundingMode::FLOOR,    '-1.24'],
            'HALF_UP half pos' => ['1.235', 2, RoundingMode::HALF_UP,   '1.24'],
            'HALF_UP half neg' => ['-1.235', 2, RoundingMode::HALF_UP,  '-1.24'],
            'HALF_DOWN half pos' => ['1.235', 2, RoundingMode::HALF_DOWN, '1.23'],
            'HALF_DOWN half neg' => ['-1.235', 2, RoundingMode::HALF_DOWN, '-1.23'],
            'HALF_EVEN even digit' => ['1.245', 2, RoundingMode::HALF_EVEN, '1.24'],
            'HALF_EVEN odd digit' => ['1.255', 2, RoundingMode::HALF_EVEN, '1.26'],
            'HALF_EVEN exact' => ['1.230', 2, RoundingMode::HALF_EVEN, '1.23'],
        ];
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function testCompareTo(): void
    {
        self::assertSame(0, BigDecimal::of('1.50')->compareTo('1.5'));
        self::assertSame(1, BigDecimal::of('1.6')->compareTo('1.5'));
        self::assertSame(-1, BigDecimal::of('1.4')->compareTo('1.5'));
    }

    public function testCompareToScaleIndependent(): void
    {
        self::assertTrue(BigDecimal::of('1.50')->isEqualTo('1.5'));
        self::assertTrue(BigDecimal::of('1.500')->isEqualTo('1.5'));
    }

    public function testIsEqualTo(): void
    {
        self::assertTrue(BigDecimal::of('1.5')->isEqualTo('1.5'));
        self::assertFalse(BigDecimal::of('1.5')->isEqualTo('1.6'));
    }

    public function testIsLessThan(): void
    {
        self::assertTrue(BigDecimal::of('1.4')->isLessThan('1.5'));
        self::assertFalse(BigDecimal::of('1.5')->isLessThan('1.5'));
    }

    public function testIsGreaterThan(): void
    {
        self::assertTrue(BigDecimal::of('1.6')->isGreaterThan('1.5'));
        self::assertFalse(BigDecimal::of('1.5')->isGreaterThan('1.5'));
    }

    public function testIsLessThanOrEqualTo(): void
    {
        self::assertTrue(BigDecimal::of('1.5')->isLessThanOrEqualTo('1.5'));
        self::assertTrue(BigDecimal::of('1.4')->isLessThanOrEqualTo('1.5'));
        self::assertFalse(BigDecimal::of('1.6')->isLessThanOrEqualTo('1.5'));
    }

    public function testIsGreaterThanOrEqualTo(): void
    {
        self::assertTrue(BigDecimal::of('1.5')->isGreaterThanOrEqualTo('1.5'));
        self::assertTrue(BigDecimal::of('1.6')->isGreaterThanOrEqualTo('1.5'));
        self::assertFalse(BigDecimal::of('1.4')->isGreaterThanOrEqualTo('1.5'));
    }

    public function testIsZero(): void
    {
        self::assertTrue(BigDecimal::of('0')->isZero());
        self::assertTrue(BigDecimal::of('0.00')->isZero());
        self::assertFalse(BigDecimal::of('0.01')->isZero());
    }

    public function testIsPositive(): void
    {
        self::assertTrue(BigDecimal::of('0.01')->isPositive());
        self::assertFalse(BigDecimal::of('0')->isPositive());
        self::assertFalse(BigDecimal::of('-0.01')->isPositive());
    }

    public function testIsNegative(): void
    {
        self::assertTrue(BigDecimal::of('-0.01')->isNegative());
        self::assertFalse(BigDecimal::of('0')->isNegative());
        self::assertFalse(BigDecimal::of('0.01')->isNegative());
    }

    // -------------------------------------------------------------------------
    // Conversion
    // -------------------------------------------------------------------------

    public function testToInt(): void
    {
        self::assertSame(123, BigDecimal::of('123.99')->toInt());
        self::assertSame(-3, BigDecimal::of('-3.7')->toInt());
    }

    public function testToIntOverflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        BigDecimal::of('99999999999999999999')->toInt();
    }

    public function testToFloat(): void
    {
        self::assertEqualsWithDelta(1.5, BigDecimal::of('1.5')->toFloat(), 1e-10);
    }

    public function testToString(): void
    {
        self::assertSame('123.45', BigDecimal::of('123.45')->toString());
    }

    public function testStringCast(): void
    {
        self::assertSame('123.45', (string) BigDecimal::of('123.45'));
    }

    public function testToBigInteger(): void
    {
        self::assertSame('123', BigDecimal::of('123.99')->toBigInteger()->toString());
    }

    // -------------------------------------------------------------------------
    // Scientific notation
    // -------------------------------------------------------------------------

    /**
     * @param string $value
     * @param string $expected
     */
    #[DataProvider('scientificProvider')]
    public function testToScientific(string $value, string $expected): void
    {
        self::assertSame($expected, BigDecimal::of($value)->toScientific());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function scientificProvider(): array
    {
        return [
            'zero' => ['0',       '0E+0'],
            'integer' => ['123',     '1.23E+2'],
            'one' => ['1',       '1E+0'],
            'hundred' => ['100',     '1E+2'],
            'decimal' => ['123.456', '1.23456E+2'],
            'small decimal' => ['0.00123', '1.23E-3'],
            'negative' => ['-1.5',    '-1.5E+0'],
            'negative small' => ['-0.005',  '-5E-3'],
        ];
    }

    // -------------------------------------------------------------------------
    // Immutability
    // -------------------------------------------------------------------------

    public function testImmutability(): void
    {
        $a = BigDecimal::of('10.5');
        $b = $a->add('1.5');

        self::assertSame('10.5', $a->toString());
        self::assertSame('12.0', $b->toString());
    }

    // -------------------------------------------------------------------------
    // Financial calculation accuracy
    // -------------------------------------------------------------------------

    public function testNoFloatingPointRoundingError(): void
    {
        // 0.1 + 0.2 must be exactly 0.3 — not 0.30000000000000004
        $result = BigDecimal::of('0.1')->add('0.2');
        self::assertSame('0.3', $result->toString());
    }

    public function testFinancialDivision(): void
    {
        // 1 / 3 to 10 decimal places
        $result = BigDecimal::of('1')->dividedBy('3', 10, RoundingMode::HALF_UP);
        self::assertSame('0.3333333333', $result->toString());
    }

    public function testBankerRounding(): void
    {
        // Banker's rounding: round half to even
        self::assertSame('2.4', BigDecimal::of('2.45')->round(1, RoundingMode::HALF_EVEN)->toString());
        self::assertSame('2.6', BigDecimal::of('2.55')->round(1, RoundingMode::HALF_EVEN)->toString());
    }

    public function testLargeDecimalPrecision(): void
    {
        $a = BigDecimal::of('123456789012345678901234567890.12345678901234567890');
        $b = BigDecimal::of('987654321098765432109876543210.98765432109876543210');
        $sum = $a->add($b);

        self::assertSame('1111111110111111111011111111101.11111111011111111100', $sum->toString());
    }
}
