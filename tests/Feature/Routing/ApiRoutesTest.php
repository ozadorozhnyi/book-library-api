<?php

namespace Tests\Feature\Routing;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins the registered route table.
 *
 * Catches accidental route deletions or method changes — for example,
 * if someone removes `Route::apiResource('books', ...)` and replaces
 * it with `Route::resource(...)` (which adds web-only routes), this
 * test fails fast.
 */
final class ApiRoutesTest extends TestCase
{
    /**
     * Each row of the data provider is one Route::apiResource entry; if
     * the URI or HTTP method drifts, the test fails for that case only.
     */
    #[DataProvider('expectedRouteProvider')]
    public function test_route_is_registered_with_correct_methods(
        string $method,
        string $uri,
        string $action,
    ): void {
        $route = Route::getRoutes()->getByAction($action);

        $this->assertNotNull($route, "Route {$action} is not registered");
        $this->assertSame($uri, $route->uri());
        $this->assertContains($method, $route->methods());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function expectedRouteProvider(): array
    {
        $controller = \App\Http\Controllers\Api\BookController::class;

        return [
            'index'   => ['GET',    'api/v1/books',        $controller . '@index'],
            'store'   => ['POST',   'api/v1/books',        $controller . '@store'],
            'show'    => ['GET',    'api/v1/books/{book}', $controller . '@show'],
            'update'  => ['PUT',    'api/v1/books/{book}', $controller . '@update'],
            'patch'   => ['PATCH',  'api/v1/books/{book}', $controller . '@update'],
            'destroy' => ['DELETE', 'api/v1/books/{book}', $controller . '@destroy'],
        ];
    }

    /**
     * Defence in depth: verifies no `Route::resource('books', ...)` (web)
     * sneaked in alongside the api-only resource — every books route must
     * live under the /api prefix.
     */
    public function test_no_book_routes_outside_api_prefix(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_contains($route->uri(), 'books')) {
                $this->assertStringStartsWith(
                    'api/',
                    $route->uri(),
                    "Found a non-API books route: {$route->uri()}",
                );
            }
        }
    }
}
