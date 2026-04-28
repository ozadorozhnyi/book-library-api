<?php

namespace Tests\Feature\Api\Books;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PUT|PATCH /api/v1/books/{book} — partial and full update semantics.
 */
final class UpdateBookTest extends TestCase
{
    use RefreshDatabase;

    /** PATCH with one field updates that field and returns the fresh resource. */
    public function test_patches_single_field(): void
    {
        $book = Book::factory()->create(['title' => 'Original']);

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['title' => 'Renamed']);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Renamed');
    }

    /**
     * Fields not present in the PATCH payload are kept as-is — this is
     * the core PATCH contract that distinguishes it from PUT.
     */
    public function test_only_provided_fields_are_modified(): void
    {
        $book = Book::factory()->create([
            'title' => 'Original',
            'author' => 'Author A',
            'publisher' => 'Publisher A',
        ]);

        $this->patchJson("/api/v1/books/{$book->id}", ['title' => 'Renamed']);

        $book->refresh();
        $this->assertSame('Renamed', $book->title);
        $this->assertSame('Author A', $book->author);
        $this->assertSame('Publisher A', $book->publisher);
    }

    /**
     * PUT and PATCH share the same controller method; both must work
     * with sometimes-wrapped rules. Some clients only know PUT.
     */
    public function test_put_method_works_for_partial_update(): void
    {
        $book = Book::factory()->create(['title' => 'Original']);

        $response = $this->putJson("/api/v1/books/{$book->id}", ['title' => 'Via PUT']);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Via PUT');
    }

    /** Empty PATCH body is a valid no-op; the resource stays untouched. */
    public function test_empty_patch_is_a_valid_no_op(): void
    {
        $book = Book::factory()->create();
        $originalTitle = $book->title;

        $response = $this->patchJson("/api/v1/books/{$book->id}", []);

        $response->assertOk();
        $this->assertSame($originalTitle, $book->fresh()->title);
    }

    /** Unknown id yields the same neutral 404 envelope as show/destroy. */
    public function test_returns_404_for_unknown_id(): void
    {
        $response = $this->patchJson('/api/v1/books/9999', ['title' => 'Whatever']);

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'Resource not found']);
    }

    /** A field that violates its rule produces a 422 even on PATCH. */
    public function test_returns_422_for_invalid_field(): void
    {
        $book = Book::factory()->create();

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['price_usd' => -5]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['price_usd']);
    }

    /**
     * Verifies that $book->fresh() in the controller actually returns
     * the post-update state — updated_at must change, not just title.
     */
    public function test_response_includes_fresh_data_after_update(): void
    {
        $book = Book::factory()->create(['title' => 'Original']);
        $originalUpdatedAt = $book->updated_at;

        sleep(1);

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['title' => 'New']);

        $response->assertJsonPath('data.title', 'New');
        $this->assertNotSame(
            $originalUpdatedAt->toIso8601String(),
            $response->json('data.updated_at'),
        );
    }

    /** decimal:0,2 silent-truncation guard applies to PATCH as well as POST. */
    public function test_rejects_three_decimal_price_on_update(): void
    {
        $book = Book::factory()->create();

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['price_usd' => 19.999]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['price_usd']);
    }
}
