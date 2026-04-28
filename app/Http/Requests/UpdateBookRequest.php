<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT|PATCH /api/v1/books/{book} — partial or full update.
 *
 * Each field is wrapped with `sometimes` so a payload may include
 * only the fields it wants to change. When a field IS sent the rest
 * of its rules still apply (a non-empty `title` must still be a
 * string of ≤ 255 chars).
 */
final class UpdateBookRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'publisher' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'genre' => ['sometimes', 'required', 'string', 'max:100'],
            'publication_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'word_count' => ['sometimes', 'required', 'integer', 'min:1'],
            /*
             * decimal:0,2 — accept up to 2 fractional digits. Plain `numeric`
             * would silently accept "19.999"; the DECIMAL(10,2) column would
             * truncate it to 19.99, hiding data loss from the client.
             */
            'price_usd' => ['sometimes', 'required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
