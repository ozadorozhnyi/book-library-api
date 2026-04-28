<?php

namespace App\Enums;

/**
 * Pagination page-size policy for collection endpoints.
 *
 * Cases hold the canonical numeric limits and {@see clamp()} encapsulates
 * the request-to-page-size translation. Centralising both the values and
 * the clamping logic keeps controllers thin and gives any other consumer
 * (commands, jobs, tests) a single source of truth — no duplicated
 * min/max constants scattered across the codebase.
 */
enum PaginationSize: int
{
    case DEFAULT = 20;
    case MIN = 1;
    case MAX = 100;

    /**
     * Translate a raw user-supplied value into a safe page size.
     *
     * Behaviour:
     *   - null / missing → {@see DEFAULT}
     *   - non-numeric    → cast to 0 → bumped up to {@see MIN}
     *   - below MIN      → {@see MIN}
     *   - above MAX      → {@see MAX}
     *
     * The method intentionally does not throw on out-of-range input —
     * silent clamping is friendlier than a 422 for a non-critical
     * pagination knob, and protects the database from oversized queries.
     */
    public static function clamp(int|string|null $requested): int
    {
        $value = $requested === null ? self::DEFAULT->value : (int) $requested;

        return min(
            max($value, self::MIN->value),
            self::MAX->value,
        );
    }
}
