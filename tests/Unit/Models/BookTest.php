<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the {@see Book} Eloquent model.
 *
 * Focus: schema invariants and casts — the model is the boundary
 * between PHP-land and the database. If any cast or fillable list
 * drifts the API contract breaks silently.
 */
final class BookTest extends TestCase
{
    use RefreshDatabase;

    /** Pins the table name so an accidental rename surfaces immediately. */
    public function test_uses_books_table(): void
    {
        $this->assertSame('books', (new Book())->getTable());
    }

    /** Guards the mass-assignment allowlist — adding a field requires updating $fillable. */
    public function test_fillable_matches_business_attributes(): void
    {
        $this->assertSame(
            ['title', 'publisher', 'author', 'genre', 'publication_date', 'word_count', 'price_usd'],
            (new Book())->getFillable(),
        );
    }

    /** Eloquent must hydrate publication_date as Carbon (not string) for the API to format it. */
    public function test_publication_date_is_cast_to_carbon(): void
    {
        $book = Book::factory()->create(['publication_date' => '2020-05-15']);

        $fresh = $book->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $fresh->publication_date);
        $this->assertSame('2020-05-15', $fresh->publication_date->format('Y-m-d'));
    }

    /** word_count comes back as a real PHP int, not a numeric string from PDO. */
    public function test_word_count_is_cast_to_integer(): void
    {
        $book = Book::factory()->create(['word_count' => 12345]);

        $this->assertSame(12345, $book->fresh()->word_count);
    }

    /**
     * decimal:2 cast guarantees the API and database both speak the same
     * currency-safe two-digit string format — never a float that could
     * silently lose precision (0.1 + 0.2 != 0.3).
     */
    public function test_price_usd_is_cast_to_two_decimal_string(): void
    {
        $book = Book::factory()->create(['price_usd' => 19.5]);

        $this->assertSame('19.50', $book->fresh()->price_usd);
    }

    /** Factory output is actually persistable end-to-end (real INSERT, real id). */
    public function test_factory_creates_persistable_book(): void
    {
        $book = Book::factory()->create();

        $this->assertNotNull($book->id);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    /** created_at / updated_at are populated automatically by Eloquent. */
    public function test_model_includes_timestamps(): void
    {
        $book = Book::factory()->create();
        $fresh = $book->fresh();

        $this->assertNotNull($fresh->created_at);
        $this->assertNotNull($fresh->updated_at);
    }
}
