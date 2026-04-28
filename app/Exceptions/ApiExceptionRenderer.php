<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders /api/* exceptions as a minimal JSON envelope.
 *
 * Goals:
 *   1. Never leak stack traces, file paths or class names to API clients
 *      — even when APP_DEBUG=true. The full trace is still recorded in
 *      storage/logs/laravel.log for the developer.
 *   2. Strip Laravel-generated 404 messages that disclose model class
 *      names ("No query results for model [App\\Models\\Book] 9999")
 *      while preserving messages the application produces explicitly
 *      via abort(404, '...').
 *   3. Leave ValidationException to Laravel's default renderer so the
 *      response keeps its standard {message, errors{}} shape.
 *
 * Wired up in bootstrap/app.php through $exceptions->render(...).
 */
final class ApiExceptionRenderer
{
    /**
     * Render an exception thrown from an /api/* route as a JSON envelope.
     *
     * Returns `null` to defer to Laravel's default renderer when this
     * class is not responsible for the exception (web routes,
     * ValidationException — see {@see shouldRender()}).
     *
     * @return JsonResponse|null  JSON `{message: string}` payload with the
     *                            resolved HTTP status, or null when the
     *                            renderer chooses not to handle this case.
     */
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->shouldRender($request, $e)) {
            return null;
        }

        $status = $this->resolveStatus($e);
        $message = $this->resolveMessage($e, $status);

        return response()->json(['message' => $message], $status);
    }

    /**
     * Decide whether this renderer is responsible for the exception.
     *
     * Web routes are out of scope; ValidationException is handled by
     * Laravel's default renderer so its rich {errors:{}} payload is
     * preserved.
     */
    private function shouldRender(Request $request, Throwable $e): bool
    {
        if (! $request->is('api/*')) {
            return false;
        }

        if ($e instanceof ValidationException) {
            return false;
        }

        return true;
    }

    /**
     * Map an exception to the HTTP status code we will return.
     *
     * Order matters: ModelNotFoundException and NotFoundHttpException
     * are pinned to 404 explicitly because we do not want a future
     * subclass that implements HttpExceptionInterface with a different
     * status to silently change the contract. Any other
     * HttpExceptionInterface (e.g. MethodNotAllowedHttpException → 405)
     * preserves its declared status. Everything else collapses to 500.
     */
    private function resolveStatus(Throwable $e): int
    {
        return match (true) {
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => 404,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    /**
     * Build the user-facing message string for the response body.
     *
     * Routing differs by category:
     *   - 404 from a missing model or route   → sanitised via {@see safeNotFoundMessage()}
     *   - 5xx server errors                   → flat 'Server error' (never leak internals)
     *   - other 4xx (e.g. 405, 403, custom)   → exception's own message, falling back to 'Error'
     *
     * @param  int  $status  the status already chosen by {@see resolveStatus()}.
     */
    private function resolveMessage(Throwable $e, int $status): string
    {
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->safeNotFoundMessage($e->getMessage());
        }

        if ($status >= 500) {
            return 'Server error';
        }

        return $e->getMessage() ?: 'Error';
    }

    /**
     * Drop Laravel-generated 404 strings that leak internal detail
     * and keep messages the application produced itself.
     *
     * Laravel-generated patterns we strip:
     *   - "No query results for model [App\\Models\\X] N"  (route model bind)
     *   - "The route api/v1/foo could not be found."        (unmatched route)
     */
    private function safeNotFoundMessage(string $message): string
    {
        $isLaravelGenerated = str_starts_with($message, 'No query results for model')
            || str_starts_with($message, 'The route ');

        return ($isLaravelGenerated || $message === '')
            ? 'Resource not found'
            : $message;
    }
}
