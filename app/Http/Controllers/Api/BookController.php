<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaginationSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class BookController extends Controller
{
    /**
     * GET /api/v1/books — paginated list of books.
     *
     * The page size comes from ?per_page (defaulted and clamped by
     * {@see PaginationSize::clamp()} — controllers stay free of magic
     * numbers and clamping arithmetic).
     *
     * If the requested ?page exceeds the last available page (and the
     * dataset is not empty) we return 404 — asking for "page 5" of a
     * 3-page result is a client error, not an empty success.
     *
     * @return AnonymousResourceCollection wrapping {@see BookResource}
     */
    #[OA\Get(
        path: '/api/v1/books',
        summary: 'List books with pagination',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1),
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Page size; clamped to [1, 100].',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated collection of books',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Book'),
                        ),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Requested page is out of range',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
            ),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = PaginationSize::clamp($request->input('per_page'));

        $paginator = Book::orderBy('id')->paginate($perPage);

        if ($request->integer('page', 1) > $paginator->lastPage() && $paginator->total() > 0) {
            abort(Response::HTTP_NOT_FOUND, 'Page out of range');
        }

        return BookResource::collection($paginator);
    }

    /**
     * GET /api/v1/books/{book} — single book by id.
     *
     * Route model binding resolves {book} → Book; a missing id raises
     * ModelNotFoundException, which {@see \App\Exceptions\ApiExceptionRenderer}
     * renders as a JSON 404.
     */
    #[OA\Get(
        path: '/api/v1/books/{book}',
        summary: 'Get a book by id',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'book',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Book found',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Book')],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Book not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
            ),
        ],
    )]
    public function show(Book $book): BookResource
    {
        return new BookResource($book);
    }

    /**
     * POST /api/v1/books — create a new book.
     *
     * Validation lives in {@see StoreBookRequest}; on failure Laravel
     * returns 422 with field-level errors. Only validated fields are
     * mass-assigned (defence in depth alongside Book::$fillable).
     */
    #[OA\Post(
        path: '/api/v1/books',
        summary: 'Create a new book',
        tags: ['Books'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BookPayload'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Book created',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Book')],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = Book::create($request->validated());

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PUT|PATCH /api/v1/books/{book} — partial or full update.
     *
     * {@see UpdateBookRequest} wraps base rules with `sometimes` so the
     * same endpoint serves PATCH (partial) and PUT (full) without the
     * client having to resend untouched fields.
     *
     * `$book->fresh()` reloads the row to surface any DB-side mutations
     * (defaults, triggers, updated_at) instead of returning the stale
     * in-memory copy.
     */
    #[OA\Patch(
        path: '/api/v1/books/{book}',
        summary: 'Update a book (partial or full)',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'book',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Any subset of the writable Book attributes.',
            content: new OA\JsonContent(ref: '#/components/schemas/BookPayload'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Book updated',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Book')],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Book not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $book->update($request->validated());

        return new BookResource($book->fresh());
    }

    /**
     * DELETE /api/v1/books/{book} — delete a book.
     *
     * Returns 204 No Content per REST conventions: the resource is gone,
     * there is nothing meaningful left to send back.
     */
    #[OA\Delete(
        path: '/api/v1/books/{book}',
        summary: 'Delete a book',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'book',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Book deleted'),
            new OA\Response(
                response: 404,
                description: 'Book not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage'),
            ),
        ],
    )]
    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent();
    }
}
