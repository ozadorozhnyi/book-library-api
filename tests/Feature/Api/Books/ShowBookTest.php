<?php

namespace Tests\Feature\Api\Books;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/books/{book} — single resource fetch.
 */
final class ShowBookTest extends TestCase
{
    use RefreshDatabase;

    /** Existing id resolves through route model binding into a 200 response. */
    public function test_returns_existing_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $book->id);
        $response->assertJsonPath('data.title', $book->title);
    }

    /** Unknown id yields a 404 with the canonical neutral message. */
    public function test_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/api/v1/books/9999');

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'Resource not found']);
    }

    /**
     * Security: the 404 payload must not contain the model class name
     * or any "No query results" phrase that hints at internal structure.
     */
    public function test_404_does_not_leak_model_class_name(): void
    {
        $response = $this->getJson('/api/v1/books/9999');

        $body = $response->json();
        $this->assertSame(['message' => 'Resource not found'], $body);
        $this->assertStringNotContainsString('App\\Models', json_encode($body));
        $this->assertStringNotContainsString('No query results', json_encode($body));
    }

    /** Single-resource response is wrapped under `data` (consistent with collections). */
    public function test_response_wraps_book_in_data_key(): void
    {
        $book = Book::factory()->create();

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertJsonStructure([
            'data' => ['id', 'title', 'publisher', 'author', 'genre', 'publication_date', 'word_count', 'price_usd', 'created_at', 'updated_at'],
        ]);
    }
}
