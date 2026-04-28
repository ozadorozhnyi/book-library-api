<?php

namespace Tests\Feature\Api\Books;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/books — pagination contract.
 */
final class ListBooksTest extends TestCase
{
    use RefreshDatabase;

    /** Pins the public envelope: data array + Laravel pagination meta and links. */
    public function test_returns_200_with_paginated_payload(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                ['id', 'title', 'publisher', 'author', 'genre', 'publication_date', 'word_count', 'price_usd', 'created_at', 'updated_at'],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
        ]);
    }

    /** Without ?per_page the page size matches PaginationSize::DEFAULT (20). */
    public function test_default_per_page_is_20(): void
    {
        Book::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertJsonPath('meta.per_page', 20);
        $this->assertCount(20, $response->json('data'));
    }

    /** A valid in-range ?per_page is honoured exactly. */
    public function test_per_page_query_is_respected(): void
    {
        Book::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/books?per_page=5');

        $response->assertJsonPath('meta.per_page', 5);
        $this->assertCount(5, $response->json('data'));
    }

    /** ?per_page > MAX is silently capped at 100 — DoS guard. */
    public function test_per_page_clamps_at_max_100(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=99999');

        $response->assertJsonPath('meta.per_page', 100);
    }

    /** ?per_page < MIN is bumped up to 1 — Eloquent paginate(0) blows up otherwise. */
    public function test_per_page_clamps_at_min_1(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=0');

        $response->assertJsonPath('meta.per_page', 1);
    }

    /** Empty database is a legitimate 200 response, not a 404. */
    public function test_returns_empty_data_when_no_books(): void
    {
        $response = $this->getJson('/api/v1/books');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $response->assertJsonPath('meta.total', 0);
    }

    /**
     * Asking for a page beyond last_page on a non-empty dataset is a
     * client error → 404 with the application abort message preserved.
     */
    public function test_returns_404_when_page_out_of_range(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=10&page=2');

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'Page out of range']);
    }

    /**
     * total=0 short-circuits the page-out-of-range check, so a client
     * polling page 1 of an empty set still gets a clean 200.
     */
    public function test_page_one_with_empty_dataset_is_not_404(): void
    {
        $response = $this->getJson('/api/v1/books?page=1');

        $response->assertOk();
    }

    /** Content-Type is application/json (JSON-only API). */
    public function test_response_is_json(): void
    {
        Book::factory()->create();

        $response = $this->getJson('/api/v1/books');

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    /**
     * Pagination order is stable: rows come back ordered by id ASC,
     * regardless of the order in which they were inserted.
     */
    public function test_books_are_ordered_by_id_ascending(): void
    {
        $third = Book::factory()->create();
        $first = Book::factory()->create();
        $second = Book::factory()->create();

        $response = $this->getJson('/api/v1/books');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$third->id, $first->id, $second->id], $ids);
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
    }

    /** Boundary value: per_page=1 (exact MIN) is honoured, not bumped. */
    public function test_per_page_at_min_boundary(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=1');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 1);
        $this->assertCount(1, $response->json('data'));
    }

    /** Boundary value: per_page=100 (exact MAX) is honoured, not clamped. */
    public function test_per_page_at_max_boundary(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=100');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
    }

    /** Boundary value: per_page=101 (one above MAX) is clamped to 100. */
    public function test_per_page_just_above_max_is_clamped(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=101');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
    }

    /**
     * Non-numeric per_page (e.g. ?per_page=abc) casts to 0 in PHP and
     * is then floored to MIN — defends against URL tampering.
     */
    public function test_per_page_non_numeric_string_floors_to_min(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?per_page=abc');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 1);
    }

    /**
     * page=0 is accepted: Laravel's paginator clamps non-positive page
     * numbers to 1 internally, so the response is 200 with current_page=1.
     */
    public function test_page_zero_is_treated_as_page_one(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?page=0');

        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 1);
    }

    /**
     * Negative ?page is also normalised to page 1 — no 400/422, just a
     * graceful 200 with the first page.
     */
    public function test_negative_page_is_treated_as_page_one(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books?page=-1');

        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 1);
    }
}
