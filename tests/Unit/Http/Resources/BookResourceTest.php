<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unit tests for {@see BookResource}.
 *
 * Pins the public JSON shape returned by the API. Any drift (renamed
 * field, dropped attribute, format change) breaks this test loudly.
 */
final class BookResourceTest extends TestCase
{
    use RefreshDatabase;

    /** Pins the exact field set and ordering exposed to API clients. */
    public function test_resource_includes_expected_fields(): void
    {
        $book = Book::factory()->create([
            'title' => 'The Hobbit',
            'publisher' => 'Allen & Unwin',
            'author' => 'J. R. R. Tolkien',
            'genre' => 'Fantasy',
            'publication_date' => '1937-09-21',
            'word_count' => 95022,
            'price_usd' => 14.99,
        ]);

        $array = (new BookResource($book))->toArray(Request::create('/'));

        $this->assertSame([
            'id', 'title', 'publisher', 'author', 'genre',
            'publication_date', 'word_count', 'price_usd',
            'created_at', 'updated_at',
        ], array_keys($array));
    }

    /** Date format is ISO Y-m-d (no time, no timezone) for client display. */
    public function test_publication_date_is_iso_y_m_d(): void
    {
        $book = Book::factory()->create(['publication_date' => '2020-05-15']);

        $array = (new BookResource($book))->toArray(Request::create('/'));

        $this->assertSame('2020-05-15', $array['publication_date']);
    }

    /**
     * Price is always a string with two fractional digits, never a float
     * — the OpenAPI schema declares it as `string` for that reason.
     */
    public function test_price_usd_is_decimal_string_with_two_places(): void
    {
        $book = Book::factory()->create(['price_usd' => 9.5]);

        $array = (new BookResource($book))->toArray(Request::create('/'));

        $this->assertSame('9.50', $array['price_usd']);
        $this->assertIsString($array['price_usd']);
    }

    /** word_count round-trips as int (cast on the model), not as numeric string. */
    public function test_word_count_stays_integer(): void
    {
        $book = Book::factory()->create(['word_count' => 12345]);

        $array = (new BookResource($book))->toArray(Request::create('/'));

        $this->assertSame(12345, $array['word_count']);
        $this->assertIsInt($array['word_count']);
    }

    /** Timestamps follow ISO 8601 with timezone offset, e.g. 2026-04-28T17:08:38+00:00. */
    public function test_timestamps_are_iso8601(): void
    {
        $book = Book::factory()->create();

        $array = (new BookResource($book))->toArray(Request::create('/'));

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
            $array['created_at'],
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
            $array['updated_at'],
        );
    }
}
