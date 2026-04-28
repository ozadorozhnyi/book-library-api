<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Public JSON shape for a single Book.
 *
 * The OpenAPI `Book` schema is co-located here because this class is
 * the authoritative definition of what the API actually returns —
 * keeping the contract next to the code that produces it minimises
 * the chance of the two drifting apart.
 */
#[OA\Schema(
    schema: 'Book',
    type: 'object',
    required: [
        'id', 'title', 'publisher', 'author', 'genre',
        'publication_date', 'word_count', 'price_usd',
        'created_at', 'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'The Hobbit'),
        new OA\Property(property: 'publisher', type: 'string', example: 'Allen & Unwin'),
        new OA\Property(property: 'author', type: 'string', example: 'J. R. R. Tolkien'),
        new OA\Property(property: 'genre', type: 'string', example: 'Fantasy'),
        new OA\Property(property: 'publication_date', type: 'string', format: 'date', example: '1937-09-21'),
        new OA\Property(property: 'word_count', type: 'integer', example: 95022),
        new OA\Property(
            property: 'price_usd',
            type: 'string',
            example: '14.99',
            description: 'Decimal string with exactly 2 fractional digits.',
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-04-28T17:08:38+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-04-28T17:08:38+00:00'),
    ],
)]
class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'publisher' => $this->publisher,
            'author' => $this->author,
            'genre' => $this->genre,
            'publication_date' => $this->publication_date->format('Y-m-d'),
            'word_count' => $this->word_count,
            'price_usd' => $this->price_usd,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
