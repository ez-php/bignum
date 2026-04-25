<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\BigNum\Backend\GmpBackend;
use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\BigNum\DivisionByZeroException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * @covers \EzPhp\BigNum\BigInteger
 * @requires extension gmp
 */
#[CoversClass(BigInteger::class)]
#[UsesClass(BigDecimal::class)]
#[UsesClass(GmpBackend::class)]
#[UsesClass(DivisionByZeroException::class)]
#[RequiresPhpExtension('gmp')]
final class BigIntegerTest extends TestCase
{
    protected function setUp(): void
    {
        // Use GmpBackend for all tests (GMP is always available as a built-in PHP extension)
        BigInteger::setDefaultBackend(new GmpBackend());
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public function testOfInt(): void
    {
        self::assertSame('42', BigInteger::of(42)->toString());
        self::assertSame('-7', BigInteger::of(-7)->toString());
        self::assertSame('0', BigInteger::of(0)->toString());
    }

    public function testOfString(): void
    {
        self::assertSame('12345', BigInteger::of('12345')->toString());
        self::assertSame('-999', BigInteger::of('-999')->toString());
        self::assertSame('0', BigInteger::of('0')->toString());
    }

    public function testOfNormalizesLeadingZeros(): void
    {
        self::assertSame('42', BigInteger::of('0042')->toString());
        self::assertSame('-7', BigInteger::of('-007')->toString());
        self::assertSame('0', BigInteger::of('00')->toString());
    }

    public function testOfRejectsInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigInteger::of('12.34');
    }

    public function testOfRejectsLetters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigInteger::of('abc');
    }

    public function testZeroFactory(): void
    {
        self::assertTrue(BigInteger::zero()->isZero());
        self::assertSame('0', BigInteger::zero()->toString());
    }

    public function testOneFactory(): void
    {
        self::assertSame('1', BigInteger::one()->toString());
    }

    public function testLargeNumber(): void
    {
        $large = '99999999999999999999999999999999999999999999999999';
        self::assertSame($large, BigInteger::of($large)->toString());
    }

    // -------------------------------------------------------------------------
    // Arithmetic
    // -------------------------------------------------------------------------

    public function testAdd(): void
    {
        self::assertSame('10', BigInteger::of(3)->add(7)->toString());
        self::assertSame('0', BigInteger::of(-5)->add(5)->toString());
        self::assertSame('-8', BigInteger::of(-3)->add(-5)->toString());
    }

    public function testAddString(): void
    {
        self::assertSame('100', BigInteger::of('50')->add('50')->toString());
    }

    public function testSubtract(): void
    {
        self::assertSame('-4', BigInteger::of(3)->subtract(7)->toString());
        self::assertSame('10', BigInteger::of(15)->subtract(5)->toString());
    }

    public function testMultiply(): void
    {
        self::assertSame('42', BigInteger::of(6)->multiply(7)->toString());
        self::assertSame('-12', BigInteger::of(-3)->multiply(4)->toString());
        self::assertSame('0', BigInteger::of(0)->multiply(999)->toString());
    }

    public function testDivide(): void
    {
        // 10 / 3 = 3 (truncated)
        self::assertSame('3', BigInteger::of(10)->divide(3)->toString());
        // -10 / 3 = -3 (truncated towards zero)
        self::assertSame('-3', BigInteger::of(-10)->divide(3)->toString());
        // 10 / -3 = -3 (truncated towards zero)
        self::assertSame('-3', BigInteger::of(10)->divide(-3)->toString());
    }

    public function testDivideByZero(): void
    {
        $this->expectException(DivisionByZeroException::class);
        BigInteger::of(5)->divide(0);
    }

    public function testMod(): void
    {
        self::assertSame('1', BigInteger::of(10)->mod(3)->toString());
        self::assertSame('-1', BigInteger::of(-10)->mod(3)->toString());
        self::assertSame('0', BigInteger::of(9)->mod(3)->toString());
    }

    public function testModByZero(): void
    {
        $this->expectException(DivisionByZeroException::class);
        BigInteger::of(5)->mod(0);
    }

    public function testPow(): void
    {
        self::assertSame('8', BigInteger::of(2)->pow(3)->toString());
        self::assertSame('1', BigInteger::of(5)->pow(0)->toString());
        self::assertSame('1', BigInteger::of(1)->pow(100)->toString());
    }

    public function testPowLarge(): void
    {
        // 2^100
        $expected = '1267650600228229401496703205376';
        self::assertSame($expected, BigInteger::of(2)->pow(100)->toString());
    }

    public function testPowNegativeExponentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigInteger::of(2)->pow(-1);
    }

    public function testAbs(): void
    {
        self::assertSame('5', BigInteger::of(-5)->abs()->toString());
        self::assertSame('5', BigInteger::of(5)->abs()->toString());
        self::assertSame('0', BigInteger::of(0)->abs()->toString());
    }

    public function testNegate(): void
    {
        self::assertSame('-5', BigInteger::of(5)->negate()->toString());
        self::assertSame('5', BigInteger::of(-5)->negate()->toString());
        self::assertSame('0', BigInteger::of(0)->negate()->toString());
    }

    public function testGcd(): void
    {
        self::assertSame('6', BigInteger::of(12)->gcd(18)->toString());
        self::assertSame('1', BigInteger::of(7)->gcd(13)->toString());
        self::assertSame('5', BigInteger::of(-5)->gcd(10)->toString());
    }

    public function testSqrt(): void
    {
        self::assertSame('3', BigInteger::of(9)->sqrt()->toString());
        self::assertSame('2', BigInteger::of(8)->sqrt()->toString()); // floor(2.828)
        self::assertSame('0', BigInteger::of(0)->sqrt()->toString());
        self::assertSame('10', BigInteger::of(100)->sqrt()->toString());
    }

    public function testSqrtOfNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigInteger::of(-1)->sqrt();
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function testCompareTo(): void
    {
        self::assertSame(0, BigInteger::of(5)->compareTo(5));
        self::assertSame(1, BigInteger::of(6)->compareTo(5));
        self::assertSame(-1, BigInteger::of(4)->compareTo(5));
    }

    public function testIsEqualTo(): void
    {
        self::assertTrue(BigInteger::of(5)->isEqualTo(5));
        self::assertFalse(BigInteger::of(5)->isEqualTo(6));
    }

    public function testIsLessThan(): void
    {
        self::assertTrue(BigInteger::of(4)->isLessThan(5));
        self::assertFalse(BigInteger::of(5)->isLessThan(5));
    }

    public function testIsGreaterThan(): void
    {
        self::assertTrue(BigInteger::of(6)->isGreaterThan(5));
        self::assertFalse(BigInteger::of(5)->isGreaterThan(5));
    }

    public function testIsLessThanOrEqualTo(): void
    {
        self::assertTrue(BigInteger::of(5)->isLessThanOrEqualTo(5));
        self::assertTrue(BigInteger::of(4)->isLessThanOrEqualTo(5));
        self::assertFalse(BigInteger::of(6)->isLessThanOrEqualTo(5));
    }

    public function testIsGreaterThanOrEqualTo(): void
    {
        self::assertTrue(BigInteger::of(5)->isGreaterThanOrEqualTo(5));
        self::assertTrue(BigInteger::of(6)->isGreaterThanOrEqualTo(5));
        self::assertFalse(BigInteger::of(4)->isGreaterThanOrEqualTo(5));
    }

    public function testIsZero(): void
    {
        self::assertTrue(BigInteger::of(0)->isZero());
        self::assertFalse(BigInteger::of(1)->isZero());
        self::assertFalse(BigInteger::of(-1)->isZero());
    }

    public function testIsPositive(): void
    {
        self::assertTrue(BigInteger::of(1)->isPositive());
        self::assertFalse(BigInteger::of(0)->isPositive());
        self::assertFalse(BigInteger::of(-1)->isPositive());
    }

    public function testIsNegative(): void
    {
        self::assertTrue(BigInteger::of(-1)->isNegative());
        self::assertFalse(BigInteger::of(0)->isNegative());
        self::assertFalse(BigInteger::of(1)->isNegative());
    }

    // -------------------------------------------------------------------------
    // Conversion
    // -------------------------------------------------------------------------

    public function testToInt(): void
    {
        self::assertSame(42, BigInteger::of(42)->toInt());
        self::assertSame(-7, BigInteger::of(-7)->toInt());
        self::assertSame(0, BigInteger::of(0)->toInt());
    }

    public function testToIntOverflowThrows(): void
    {
        $this->expectException(\OverflowException::class);
        BigInteger::of('99999999999999999999')->toInt();
    }

    public function testToFloat(): void
    {
        self::assertEqualsWithDelta(3.0, BigInteger::of(3)->toFloat(), 1e-10);
    }

    public function testToString(): void
    {
        self::assertSame('42', BigInteger::of(42)->toString());
    }

    public function testStringCast(): void
    {
        self::assertSame('42', (string) BigInteger::of(42));
    }

    public function testToBigDecimal(): void
    {
        $decimal = BigInteger::of(42)->toBigDecimal();
        self::assertSame('42', $decimal->toString());
        self::assertSame(0, $decimal->getScale());
    }

    // -------------------------------------------------------------------------
    // Immutability
    // -------------------------------------------------------------------------

    public function testImmutability(): void
    {
        $a = BigInteger::of(10);
        $b = $a->add(5);

        self::assertSame('10', $a->toString());
        self::assertSame('15', $b->toString());
    }

    // -------------------------------------------------------------------------
    // Large number arithmetic
    // -------------------------------------------------------------------------

    #[DataProvider('largeArithmeticProvider')]
    public function testLargeArithmetic(string $a, string $op, string $b, string $expected): void
    {
        $bigA = BigInteger::of($a);
        $result = match ($op) {
            '+' => $bigA->add($b),
            '-' => $bigA->subtract($b),
            '*' => $bigA->multiply($b),
            default => throw new \LogicException("Unknown operator: {$op}"),
        };

        self::assertSame($expected, $result->toString());
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function largeArithmeticProvider(): array
    {
        return [
            'large add' => [
                '99999999999999999999',
                '+',
                '1',
                '100000000000000000000',
            ],
            'large subtract' => [
                '100000000000000000000',
                '-',
                '1',
                '99999999999999999999',
            ],
            'large multiply' => [
                '999999999999999999',
                '*',
                '999999999999999999',
                '999999999999999998000000000000000001',
            ],
        ];
    }
}
