<?php

namespace Tests\Feature\Api\Books;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DELETE /api/v1/books/{book} — destroy contract.
 */
final class DeleteBookTest extends TestCase
{
    use RefreshDatabase;

    /** A successful delete actually removes the row from the books table. */
    public function test_removes_book_from_database(): void
    {
        $book = Book::factory()->create();

        $this->deleteJson("/api/v1/books/{$book->id}");

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    /** REST convention: destroy returns 204 No Content with an empty body. */
    public function test_returns_204_no_content(): void
    {
        $book = Book::factory()->create();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertNoContent();
    }

    /** Deleting an unknown id yields the same neutral 404 as the other endpoints. */
    public function test_returns_404_when_book_does_not_exist(): void
    {
        $response = $this->deleteJson('/api/v1/books/9999');

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'Resource not found']);
    }

    /**
     * REST idempotency: deleting the same id twice returns 404 the
     * second time (the resource is gone) — not a 500 or duplicate-state
     * error.
     */
    public function test_double_delete_returns_404(): void
    {
        $book = Book::factory()->create();

        $first = $this->deleteJson("/api/v1/books/{$book->id}");
        $second = $this->deleteJson("/api/v1/books/{$book->id}");

        $first->assertNoContent();
        $second->assertNotFound();
    }
}
