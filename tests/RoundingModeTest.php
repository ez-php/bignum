<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\BigNum\RoundingMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \EzPhp\BigNum\RoundingMode
 */
#[CoversClass(RoundingMode::class)]
final class RoundingModeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = RoundingMode::cases();
        $names = array_map(static fn (RoundingMode $m) => $m->name, $cases);

        self::assertContains('UP', $names);
        self::assertContains('DOWN', $names);
        self::assertContains('CEILING', $names);
        self::assertContains('FLOOR', $names);
        self::assertContains('HALF_UP', $names);
        self::assertContains('HALF_DOWN', $names);
        self::assertContains('HALF_EVEN', $names);
        self::assertCount(7, $cases);
    }

    #[DataProvider('roundingModeProvider')]
    public function testCaseByName(string $name): void
    {
        $cases = RoundingMode::cases();
        $found = null;

        foreach ($cases as $case) {
            if ($case->name === $name) {
                $found = $case;
                break;
            }
        }

        self::assertNotNull($found, "RoundingMode::{$name} must exist");
        self::assertSame($name, $found->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roundingModeProvider(): array
    {
        return [
            'UP' => ['UP'],
            'DOWN' => ['DOWN'],
            'CEILING' => ['CEILING'],
            'FLOOR' => ['FLOOR'],
            'HALF_UP' => ['HALF_UP'],
            'HALF_DOWN' => ['HALF_DOWN'],
            'HALF_EVEN' => ['HALF_EVEN'],
        ];
    }
}
