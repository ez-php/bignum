<?php

declare(strict_types=1);

namespace Tests\Backend;

use EzPhp\BigNum\Backend\BcMathBackend;
use EzPhp\BigNum\DivisionByZeroException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * @covers \EzPhp\BigNum\Backend\BcMathBackend
 * @requires extension bcmath
 */
#[CoversClass(BcMathBackend::class)]
#[UsesClass(DivisionByZeroException::class)]
#[RequiresPhpExtension('bcmath')]
final class BcMathBackendTest extends TestCase
{
    private BcMathBackend $backend;

    protected function setUp(): void
    {
        $this->backend = new BcMathBackend();
    }

    public function testAdd(): void
    {
        self::assertSame('10', $this->backend->add('3', '7'));
        self::assertSame('-2', $this->backend->add('-5', '3'));
    }

    public function testSubtract(): void
    {
        self::assertSame('-4', $this->backend->subtract('3', '7'));
        self::assertSame('8', $this->backend->subtract('3', '-5'));
    }

    public function testMultiply(): void
    {
        self::assertSame('42', $this->backend->multiply('6', '7'));
        self::assertSame('-12', $this->backend->multiply('-3', '4'));
    }

    public function testDivide(): void
    {
        self::assertSame('3', $this->backend->divide('10', '3'));
        self::assertSame('-3', $this->backend->divide('-10', '3'));
    }

    public function testDivideByZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        $this->backend->divide('5', '0');
    }

    public function testMod(): void
    {
        self::assertSame('1', $this->backend->mod('10', '3'));
        self::assertSame('-1', $this->backend->mod('-10', '3'));
        self::assertSame('0', $this->backend->mod('9', '3'));
    }

    public function testModByZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        $this->backend->mod('5', '0');
    }

    public function testPow(): void
    {
        self::assertSame('8', $this->backend->pow('2', 3));
        self::assertSame('1', $this->backend->pow('5', 0));
    }

    public function testPowNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->backend->pow('2', -1);
    }

    public function testAbs(): void
    {
        self::assertSame('5', $this->backend->abs('-5'));
        self::assertSame('5', $this->backend->abs('5'));
        self::assertSame('0', $this->backend->abs('0'));
    }

    public function testNegate(): void
    {
        self::assertSame('-5', $this->backend->negate('5'));
        self::assertSame('5', $this->backend->negate('-5'));
        self::assertSame('0', $this->backend->negate('0'));
    }

    public function testGcd(): void
    {
        self::assertSame('6', $this->backend->gcd('12', '18'));
        self::assertSame('1', $this->backend->gcd('7', '13'));
    }

    public function testGcdWithNegativeOperands(): void
    {
        // BcMathBackend::gcd() takes absolute values before the Euclidean
        // algorithm — a path GmpBackendTest does not exercise, since GMP's
        // own gcd() already normalizes sign internally.
        self::assertSame('6', $this->backend->gcd('-12', '18'));
        self::assertSame('6', $this->backend->gcd('12', '-18'));
        self::assertSame('6', $this->backend->gcd('-12', '-18'));
    }

    public function testSqrt(): void
    {
        self::assertSame('3', $this->backend->sqrt('9'));
        self::assertSame('2', $this->backend->sqrt('8'));
        self::assertSame('0', $this->backend->sqrt('0'));
    }

    public function testSqrtNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->backend->sqrt('-1');
    }

    public function testCompare(): void
    {
        self::assertSame(0, $this->backend->compare('5', '5'));
        self::assertSame(1, $this->backend->compare('6', '5'));
        self::assertSame(-1, $this->backend->compare('4', '5'));
    }

    public function testLargeNumbers(): void
    {
        $a = '99999999999999999999999999999999999999';
        $b = '1';
        self::assertSame('100000000000000000000000000000000000000', $this->backend->add($a, $b));
    }
}
