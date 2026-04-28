<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/books — every field of the Book payload is required.
 */
final class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'publisher' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'genre' => ['required', 'string', 'max:100'],
            'publication_date' => ['required', 'date', 'before_or_equal:today'],
            'word_count' => ['required', 'integer', 'min:1'],
            /*
             * decimal:0,2 — accept up to 2 fractional digits. Plain `numeric`
             * would silently accept "19.999"; the DECIMAL(10,2) column would
             * truncate it to 19.99, hiding data loss from the client.
             */
            'price_usd' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
