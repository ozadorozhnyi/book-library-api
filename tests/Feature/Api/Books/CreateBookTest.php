<?php

namespace Tests\Feature\Api\Books;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * POST /api/v1/books — create endpoint with full validation matrix.
 */
final class CreateBookTest extends TestCase
{
    use RefreshDatabase;

    /**
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

    /** Happy path: a complete, well-formed payload returns 201 with the new resource. */
    public function test_creates_book_with_valid_payload(): void
    {
        $response = $this->postJson('/api/v1/books', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'The Hobbit');
        $response->assertJsonPath('data.price_usd', '14.99');
    }

    /** A successful POST writes the row to the books table. */
    public function test_persists_book_to_database(): void
    {
        $this->postJson('/api/v1/books', $this->validPayload());

        $this->assertDatabaseHas('books', [
            'title' => 'The Hobbit',
            'author' => 'J. R. R. Tolkien',
            'word_count' => 95022,
        ]);
    }

    /** REST convention: create returns 201 Created, not 200 OK. */
    public function test_response_status_is_201(): void
    {
        $response = $this->postJson('/api/v1/books', $this->validPayload());

        $this->assertSame(201, $response->status());
    }

    /** Created resource is wrapped under `data` for consistency with show/list. */
    public function test_response_wraps_data_key(): void
    {
        $response = $this->postJson('/api/v1/books', $this->validPayload());

        $response->assertJsonStructure([
            'data' => ['id', 'title', 'publisher', 'author', 'genre', 'publication_date', 'word_count', 'price_usd', 'created_at', 'updated_at'],
        ]);
    }

    /**
     * Empty payload triggers the canonical 422 envelope with one error
     * per required field — pins the validation contract.
     */
    public function test_returns_422_for_empty_payload_with_all_field_errors(): void
    {
        $response = $this->postJson('/api/v1/books', []);

        $response->assertUnprocessable();
        $response->assertJsonStructure(['message', 'errors']);
        $response->assertJsonValidationErrors([
            'title', 'publisher', 'author', 'genre',
            'publication_date', 'word_count', 'price_usd',
        ]);
    }

    /** Validation failure must NOT leave a partial row behind. */
    public function test_does_not_persist_when_validation_fails(): void
    {
        $this->postJson('/api/v1/books', []);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * End-to-end validation matrix: every constrained field is exercised
     * through real HTTP and produces a field-specific 422.
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_returns_422_for_invalid_field(array $overrides, string $expectedField): void
    {
        $response = $this->postJson('/api/v1/books', $this->validPayload($overrides));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$expectedField]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'title too long' => [['title' => str_repeat('x', 256)], 'title'],
            'genre too long' => [['genre' => str_repeat('y', 101)], 'genre'],
            'publication_date in future' => [['publication_date' => '2099-01-01'], 'publication_date'],
            'publication_date invalid' => [['publication_date' => 'not-a-date'], 'publication_date'],
            'word_count zero' => [['word_count' => 0], 'word_count'],
            'word_count negative' => [['word_count' => -1], 'word_count'],
            'price negative' => [['price_usd' => -1], 'price_usd'],
            'price 3 decimals (silent truncation guard)' => [['price_usd' => 19.999], 'price_usd'],
        ];
    }

    /**
     * Security: a client-supplied id is ignored. $fillable does not
     * include it, so Eloquent assigns its own auto-incremented id and
     * the attacker's value (999) is silently dropped.
     */
    public function test_ignores_client_supplied_id(): void
    {
        $response = $this->postJson('/api/v1/books', $this->validPayload(['id' => 999]));

        $response->assertCreated();
        $this->assertNotSame(999, $response->json('data.id'));
    }

    /**
     * Malformed JSON body — documented real behaviour:
     *
     * Laravel does NOT raise a 400 parse error here. Instead it
     * silently treats an unparseable body as an empty payload, which
     * then fails StoreBookRequest validation with 422 + the standard
     * required-field errors. The "always JSON for /api/*" contract
     * still holds — the response is JSON, not HTML.
     *
     * If we ever want strict 400 parsing, that requires explicit
     * middleware in front of the route.
     */
    public function test_malformed_json_body_falls_through_to_422(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/books',
            content: '{this is not valid json',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * Time edge case: when the server clock crosses midnight, a payload
     * dated "today" must still validate. Carbon::setTestNow() pins the
     * "now" reference so the assertion is deterministic across runs.
     */
    public function test_today_passes_before_or_equal_today_rule(): void
    {
        Carbon::setTestNow('2026-04-28 23:59:59');

        try {
            $response = $this->postJson('/api/v1/books', $this->validPayload([
                'publication_date' => '2026-04-28',
            ]));

            $response->assertCreated();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Companion to the "today passes" test: tomorrow (relative to the
     * mocked now) must still fail, even if the wall-clock has crawled
     * very close to midnight.
     */
    public function test_tomorrow_fails_before_or_equal_today_rule_under_mock_clock(): void
    {
        Carbon::setTestNow('2026-04-28 23:59:59');

        try {
            $response = $this->postJson('/api/v1/books', $this->validPayload([
                'publication_date' => '2026-04-29',
            ]));

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['publication_date']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
