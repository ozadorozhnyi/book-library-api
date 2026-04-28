<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreBookRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit tests for {@see StoreBookRequest}.
 *
 * Validation contract for POST /api/v1/books — every field required.
 * We exercise the rules with the {@see Validator} facade rather than
 * via HTTP so the assertion targets the rule set in isolation.
 */
final class StoreBookRequestTest extends TestCase
{
    /** No auth model on this project — every request is allowed. */
    public function test_authorize_returns_true(): void
    {
        $this->assertTrue((new StoreBookRequest())->authorize());
    }

    /** Sanity check: a fully-populated, well-formed payload validates cleanly. */
    public function test_passes_with_valid_payload(): void
    {
        $validator = $this->validate($this->validPayload());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'The Hobbit',
            'publisher' => 'Allen & Unwin',
            'author' => 'J. R. R. Tolkien',
            'genre' => 'Fantasy',
            'publication_date' => '1937-09-21',
            'word_count' => 95022,
            'price_usd' => 14.99,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        return Validator::make($payload, (new StoreBookRequest())->rules());
    }

    /**
     * Every business attribute is required — dropping any one of them
     * yields a 422 with a field-specific error.
     */
    #[DataProvider('requiredFieldProvider')]
    public function test_fails_when_required_field_missing(string $field): void
    {
        $payload = $this->validPayload();
        unset($payload[$field]);

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'title' => ['title'],
            'publisher' => ['publisher'],
            'author' => ['author'],
            'genre' => ['genre'],
            'publication_date' => ['publication_date'],
            'word_count' => ['word_count'],
            'price_usd' => ['price_usd'],
        ];
    }

    /** Title obeys VARCHAR(255) — anything longer fails before hitting the DB. */
    public function test_fails_when_title_exceeds_255_chars(): void
    {
        $validator = $this->validate($this->validPayload(['title' => str_repeat('x', 256)]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    /** Genre is bounded at 100 chars (column type DECIMAL(100)). */
    public function test_fails_when_genre_exceeds_100_chars(): void
    {
        $validator = $this->validate($this->validPayload(['genre' => str_repeat('y', 101)]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('genre', $validator->errors()->toArray());
    }

    /** publication_date must parse as a date. */
    public function test_fails_when_publication_date_is_not_a_date(): void
    {
        $validator = $this->validate($this->validPayload(['publication_date' => 'not-a-date']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('publication_date', $validator->errors()->toArray());
    }

    /**
     * Business rule: a book cannot be published in the future. The
     * `before_or_equal:today` rule catches `2099-…` payloads.
     */
    public function test_fails_when_publication_date_is_in_future(): void
    {
        $future = now()->addYear()->format('Y-m-d');

        $validator = $this->validate($this->validPayload(['publication_date' => $future]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('publication_date', $validator->errors()->toArray());
    }

    /** Today's date is on the inclusive boundary of `before_or_equal:today`. */
    public function test_passes_when_publication_date_is_today(): void
    {
        $validator = $this->validate($this->validPayload(['publication_date' => now()->format('Y-m-d')]));

        $this->assertTrue($validator->passes());
    }

    /**
     * word_count must be a positive integer — defends against zero,
     * negatives, floats, and non-numeric strings.
     */
    #[DataProvider('invalidWordCountProvider')]
    public function test_fails_when_word_count_invalid(mixed $value): void
    {
        $validator = $this->validate($this->validPayload(['word_count' => $value]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('word_count', $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidWordCountProvider(): array
    {
        return [
            'zero (min:1 violation)' => [0],
            'negative' => [-1],
            'non-integer string' => ['many'],
            'float' => [1.5],
        ];
    }

    /** min:0 is inclusive — a free book (price 0.00) is valid. */
    public function test_passes_when_price_is_zero(): void
    {
        $this->assertTrue($this->validate($this->validPayload(['price_usd' => 0]))->passes());
        $this->assertTrue($this->validate($this->validPayload(['price_usd' => 0.00]))->passes());
    }

    /** Negative price is a hard error — protects against refund-style data. */
    public function test_fails_when_price_is_negative(): void
    {
        $validator = $this->validate($this->validPayload(['price_usd' => -1]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('price_usd', $validator->errors()->toArray());
    }

    /**
     * The decimal:0,2 rule rejects 19.999 instead of letting the
     * DECIMAL(10,2) column silently truncate it to 19.99 — guards
     * against silent data loss.
     */
    public function test_fails_when_price_has_more_than_two_decimals(): void
    {
        $validator = $this->validate($this->validPayload(['price_usd' => 19.999]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('price_usd', $validator->errors()->toArray());
    }

    /** Standard two-fractional-digit price is the canonical valid form. */
    public function test_passes_with_two_decimal_price(): void
    {
        $this->assertTrue($this->validate($this->validPayload(['price_usd' => 19.99]))->passes());
    }

    /** decimal:0,2 means "0 to 2" — a whole number price is valid too. */
    public function test_passes_with_integer_price(): void
    {
        $this->assertTrue($this->validate($this->validPayload(['price_usd' => 20]))->passes());
    }
}
