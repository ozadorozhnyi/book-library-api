<?php

namespace Tests\Unit\Enums;

use App\Enums\PaginationSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PaginationSize}.
 *
 * Pure value-object — no Laravel application, no database — so this
 * extends PHPUnit's TestCase directly to keep boot time minimal.
 */
final class PaginationSizeTest extends TestCase
{
    /** Pins the canonical numeric values of DEFAULT/MIN/MAX cases. */
    public function test_canonical_values(): void
    {
        $this->assertSame(20, PaginationSize::DEFAULT->value);
        $this->assertSame(1, PaginationSize::MIN->value);
        $this->assertSame(100, PaginationSize::MAX->value);
    }

    /** Missing / null input falls back to the DEFAULT case. */
    public function test_clamp_returns_default_when_null(): void
    {
        $this->assertSame(20, PaginationSize::clamp(null));
    }

    /** Values above MAX are silently capped — protects DB from oversized queries. */
    public function test_clamp_caps_at_max(): void
    {
        $this->assertSame(100, PaginationSize::clamp(99_999));
        $this->assertSame(100, PaginationSize::clamp(101));
    }

    /** Zero and negative numbers are floored to MIN — prevents Eloquent paginate(0). */
    public function test_clamp_floors_at_min(): void
    {
        $this->assertSame(1, PaginationSize::clamp(0));
        $this->assertSame(1, PaginationSize::clamp(-50));
    }

    /**
     * Values inside [MIN, MAX] pass through unchanged at every boundary.
     */
    #[DataProvider('inRangeProvider')]
    public function test_clamp_passes_through_values_in_range(int $input, int $expected): void
    {
        $this->assertSame($expected, PaginationSize::clamp($input));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function inRangeProvider(): array
    {
        return [
            'min boundary' => [1, 1],
            'just above min' => [2, 2],
            'middle' => [50, 50],
            'just below max' => [99, 99],
            'max boundary' => [100, 100],
        ];
    }

    /**
     * String inputs go through PHP's int cast: numeric strings work as
     * expected, non-numeric strings cast to 0 and are then floored to MIN.
     */
    #[DataProvider('stringInputProvider')]
    public function test_clamp_accepts_numeric_strings(string $input, int $expected): void
    {
        $this->assertSame($expected, PaginationSize::clamp($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function stringInputProvider(): array
    {
        return [
            'numeric string in range' => ['42', 42],
            'numeric string above max' => ['9999', 100],
            'numeric string below min' => ['0', 1],
            'non-numeric string casts to 0 then floors' => ['abc', 1],
            'empty string casts to 0 then floors' => ['', 1],
        ];
    }
}
