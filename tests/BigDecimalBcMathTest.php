<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\BigNum\Backend\BcMathBackend;
use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\BigNum\RoundingMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Exercises BigDecimal's arithmetic paths under BcMathBackend specifically —
 * BigDecimalTest forces GmpBackend, so without this class the bcmath-backed
 * code path (the one that lets BigDecimal work without ext-gmp) is untested.
 *
 * @covers \EzPhp\BigNum\BigDecimal
 * @requires extension bcmath
 */
#[CoversClass(BigDecimal::class)]
#[UsesClass(BigInteger::class)]
#[UsesClass(BcMathBackend::class)]
#[UsesClass(RoundingMode::class)]
#[RequiresPhpExtension('bcmath')]
final class BigDecimalBcMathTest extends TestCase
{
    protected function setUp(): void
    {
        BigDecimal::setDefaultBackend(new BcMathBackend());
    }

    public function testAddAlignsScale(): void
    {
        self::assertSame('3.30', BigDecimal::of('1.1')->add(BigDecimal::of('2.20'))->toString());
    }

    public function testSubtractAlignsScale(): void
    {
        self::assertSame('-1.10', BigDecimal::of('1.10')->subtract(BigDecimal::of('2.2'))->toString());
    }

    public function testMultiplySumsScales(): void
    {
        $result = BigDecimal::of('1.5')->multiply('2.25');
        self::assertSame('3.375', $result->toString());
        self::assertSame(3, $result->getScale());
    }

    public function testDividedByHalfUp(): void
    {
        self::assertSame('3.3333', BigDecimal::of('10')->dividedBy('3', 4, RoundingMode::HALF_UP)->toString());
    }

    public function testDividedByHalfEven(): void
    {
        self::assertSame('2.4', BigDecimal::of('2.45')->round(1, RoundingMode::HALF_EVEN)->toString());
        self::assertSame('2.6', BigDecimal::of('2.55')->round(1, RoundingMode::HALF_EVEN)->toString());
    }

    public function testDividedByNegativeValues(): void
    {
        self::assertSame('-3.3333', BigDecimal::of('-10')->dividedBy('3', 4, RoundingMode::HALF_UP)->toString());
    }

    public function testToScaleDown(): void
    {
        self::assertSame('1.23', BigDecimal::of('1.2345')->toScale(2, RoundingMode::DOWN)->toString());
    }

    public function testToScaleUp(): void
    {
        self::assertSame('1.5000', BigDecimal::of('1.5')->toScale(4)->toString());
    }

    public function testPow(): void
    {
        self::assertSame('9.61', BigDecimal::of('3.1')->pow(2)->toString());
    }

    public function testSqrtOfPerfectSquare(): void
    {
        self::assertSame('3.0000', BigDecimal::of('9')->sqrt(4)->toString());
    }

    public function testSqrtRoundsToScale(): void
    {
        self::assertSame('1.4142', BigDecimal::of('2')->sqrt(4)->toString());
    }

    public function testToInt(): void
    {
        self::assertSame(3, BigDecimal::of('3.99')->toInt());
        self::assertSame(-3, BigDecimal::of('-3.99')->toInt());
    }

    public function testToBigInteger(): void
    {
        self::assertSame('3', BigDecimal::of('3.99')->toBigInteger()->toString());
    }

    public function testCompareToAcrossScales(): void
    {
        self::assertSame(0, BigDecimal::of('1.5')->compareTo(BigDecimal::of('1.50')));
        self::assertTrue(BigDecimal::of('1.5')->isLessThan(BigDecimal::of('1.51')));
    }

    public function testNegativeThroughApplyRounding(): void
    {
        self::assertSame('-1.24', BigDecimal::of('-1.235')->round(2, RoundingMode::HALF_UP)->toString());
    }

    public function testNthRootExactCubeRoot(): void
    {
        self::assertSame('2.0000', BigDecimal::of('8')->nthRoot(3, 4)->toString());
    }

    public function testNthRootNonExact(): void
    {
        self::assertSame('1.25992', BigDecimal::of('2')->nthRoot(3, 5)->toString());
    }

    public function testPowRationalExactWhenDenominatorDividesNumerator(): void
    {
        self::assertSame('16', BigDecimal::of('4')->powRational(4, 2, 2)->toString());
    }

    public function testPowRationalLargeIntermediateScale(): void
    {
        // 1.23^9 has scale 18 before rooting; regression case for a formula
        // that would truncate the operand before taking the root.
        self::assertSame('2.538476', BigDecimal::of('1.23')->powRational(9, 2, 6)->toString());
    }

    public function testPowRationalNegativeBaseWithFractionalExponentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigDecimal::of('-4')->powRational(1, 2, 2);
    }
}
