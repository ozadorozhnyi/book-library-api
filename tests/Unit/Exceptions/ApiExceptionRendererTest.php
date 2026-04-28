<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiExceptionRenderer;
use App\Models\Book;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Unit tests for {@see ApiExceptionRenderer}.
 *
 * Pins the error-type matrix that was hand-verified at the end of
 * Stage 4 — every row in that table becomes a test here so the
 * contract cannot regress silently.
 */
final class ApiExceptionRendererTest extends TestCase
{
    private ApiExceptionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ApiExceptionRenderer();
    }

    private function apiRequest(string $path = 'api/v1/books'): Request
    {
        return Request::create('/' . $path, 'GET');
    }

    /**
     * Row 1 — a raw ModelNotFoundException is sanitised: the model FQCN
     * never reaches the client, only "Resource not found" does.
     */
    public function test_model_not_found_is_rendered_as_sanitised_404(): void
    {
        $exception = (new ModelNotFoundException())->setModel(Book::class, [9999]);

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Resource not found'],
            $response->getData(true),
        );
    }

    /**
     * Row 2 — a custom message from abort(404, '...') survives the
     * renderer untouched, because it's an application contract.
     */
    public function test_custom_abort_message_is_preserved(): void
    {
        $exception = new NotFoundHttpException('Page out of range');

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Page out of range'],
            $response->getData(true),
        );
    }

    /**
     * Row 3 — Laravel's "The route ... could not be found." message
     * leaks the URL pattern; the renderer replaces it with a neutral one.
     */
    public function test_unmatched_route_message_is_sanitised(): void
    {
        $exception = new NotFoundHttpException('The route api/v1/foo could not be found.');

        $response = ($this->renderer)($exception, $this->apiRequest('api/v1/foo'));

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Resource not found'],
            $response->getData(true),
        );
    }

    /**
     * Wrapped form: Laravel converts ModelNotFoundException into a
     * NotFoundHttpException whose message starts with "No query results
     * for model [...]" — this leak must also be neutralised.
     */
    public function test_route_model_bind_message_is_sanitised(): void
    {
        $exception = new NotFoundHttpException('No query results for model [App\\Models\\Book] 9999');

        $response = ($this->renderer)($exception, $this->apiRequest('api/v1/books/9999'));

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Resource not found'],
            $response->getData(true),
        );
    }

    /**
     * Row 4 — the renderer returns null for ValidationException so
     * Laravel's default renderer handles it and preserves the rich
     * {message, errors{}} payload.
     */
    public function test_validation_exception_is_deferred_to_laravel(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            [],
            ['title' => 'required'],
        );
        $validator->fails();
        $exception = new ValidationException($validator);

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNull($response);
    }

    /**
     * Row 5 — MethodNotAllowedHttpException carries useful info ("which
     * methods ARE supported"); we keep the original message and 405 code.
     */
    public function test_method_not_allowed_keeps_status_and_message(): void
    {
        $exception = new MethodNotAllowedHttpException(
            allow: ['GET', 'HEAD', 'POST'],
            message: 'The DELETE method is not supported for route api/v1/books. Supported methods: GET, HEAD, POST.',
        );

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNotNull($response);
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'The DELETE method is not supported for route api/v1/books. Supported methods: GET, HEAD, POST.'],
            $response->getData(true),
        );
    }

    /** Web routes are out of scope — the renderer hands them back to Laravel. */
    public function test_returns_null_for_non_api_request(): void
    {
        $exception = new NotFoundHttpException();
        $webRequest = Request::create('/some/web/page', 'GET');

        $this->assertNull(($this->renderer)($exception, $webRequest));
    }

    /**
     * Generic exceptions collapse to a flat "Server error" — never leak
     * connection strings, file paths, or any other internals to the API.
     */
    public function test_generic_throwable_is_rendered_as_500_server_error(): void
    {
        $exception = new RuntimeException('Database is on fire — secret connection string xyz');

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Server error'],
            $response->getData(true),
        );
    }

    /** Other 4xx HttpException codes (e.g. 403) keep their declared status. */
    public function test_other_http_exception_keeps_its_status(): void
    {
        $exception = new HttpException(statusCode: 403, message: 'Forbidden by policy');

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Forbidden by policy'],
            $response->getData(true),
        );
    }

    /** An empty 404 message falls back to the canonical "Resource not found". */
    public function test_empty_not_found_message_falls_back_to_default(): void
    {
        $exception = new NotFoundHttpException('');

        $response = ($this->renderer)($exception, $this->apiRequest());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Resource not found'],
            $response->getData(true),
        );
    }
}
