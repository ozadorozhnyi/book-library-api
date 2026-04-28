<?php

namespace Tests\Feature\Api\Books;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 405 Method Not Allowed — every endpoint must reject HTTP methods
 * outside its declared set, and the response must come from our
 * ApiExceptionRenderer (clean JSON, real status code).
 *
 * Pinning these cases catches a class of regressions where someone
 * accidentally registers a wider set of methods (e.g. swaps
 * `apiResource` for `resource`).
 */
final class MethodNotAllowedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each row is a method that is NOT allowed on the given path —
     * the API must answer with 405 and a JSON message.
     */
    #[DataProvider('disallowedRequestProvider')]
    public function test_returns_405_for_disallowed_method(
        string $method,
        string $uri,
    ): void {
        // We seed a book so {book} routes resolve before the method check.
        $book = Book::factory()->create();
        $uri = str_replace('{id}', (string) $book->id, $uri);

        $response = $this->json($method, $uri);

        $response->assertStatus(405);
        $response->assertJsonStructure(['message']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function disallowedRequestProvider(): array
    {
        return [
            // Collection endpoint /api/v1/books only accepts GET, HEAD, POST.
            'DELETE on collection' => ['DELETE', '/api/v1/books'],
            'PUT on collection' => ['PUT', '/api/v1/books'],
            'PATCH on collection' => ['PATCH', '/api/v1/books'],
            // Resource endpoint /api/v1/books/{book} accepts GET, HEAD, PUT, PATCH, DELETE.
            'POST on resource' => ['POST', '/api/v1/books/{id}'],
        ];
    }
}
