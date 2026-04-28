<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI 3.0 specification entry point.
 *
 * This class carries no runtime behaviour — it exists solely to host the
 * top-level `#[OA\*]` attributes (Info, Server, Tags, common Schemas)
 * that describe the API as a whole. Keeping them out of the controller
 * keeps both files focused: BookController stays a routing layer and
 * this file is the single source of truth for cross-cutting docs.
 *
 * The l5-swagger scanner picks up the attributes on every class under
 * app/ during `php artisan l5-swagger:generate`.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Book Library API',
    description: <<<'TEXT'
        REST API for managing a small library of books.

        - All endpoints live under `/api/v1`.
        - Errors are returned as `{ "message": string }`; validation errors
          additionally include `{ "errors": { field: [string, ...] } }`.
        TEXT,
    contact: new OA\Contact(name: 'Oleg Zadorozhnyi', email: 'ipfound@gmail.com'),
)]
#[OA\Server(
    url: 'http://localhost:8080',
    description: 'Local Sail environment',
)]
#[OA\Tag(
    name: 'Books',
    description: 'CRUD operations for the Book resource.',
)]
#[OA\Schema(
    schema: 'ErrorMessage',
    type: 'object',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Resource not found'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The title field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
            example: ['title' => ['The title field is required.']],
        ),
    ],
)]
#[OA\Schema(
    schema: 'BookPayload',
    type: 'object',
    description: 'Writable Book attributes accepted by POST/PATCH endpoints.',
    required: ['title', 'publisher', 'author', 'genre', 'publication_date', 'word_count', 'price_usd'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'The Hobbit'),
        new OA\Property(property: 'publisher', type: 'string', maxLength: 255, example: 'Allen & Unwin'),
        new OA\Property(property: 'author', type: 'string', maxLength: 255, example: 'J. R. R. Tolkien'),
        new OA\Property(property: 'genre', type: 'string', maxLength: 100, example: 'Fantasy'),
        new OA\Property(property: 'publication_date', type: 'string', format: 'date', example: '1937-09-21'),
        new OA\Property(property: 'word_count', type: 'integer', minimum: 1, example: 95022),
        new OA\Property(
            property: 'price_usd',
            type: 'number',
            format: 'float',
            minimum: 0,
            example: 14.99,
            description: 'Price in USD. Up to 2 fractional digits; stored as DECIMAL(10,2).',
        ),
    ],
)]
final class OpenApiSpec
{
}
