<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateBookRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit tests for {@see UpdateBookRequest}.
 *
 * PATCH semantics — every rule is wrapped with `sometimes`, so a missing
 * field is acceptable but a *present* field still has to obey the same
 * validation as on create.
 */
final class UpdateBookRequestTest extends TestCase
{
    /** No auth model on this project — every request is allowed. */
    public function test_authorize_returns_true(): void
    {
        $this->assertTrue((new UpdateBookRequest())->authorize());
    }

    /** PATCH with nothing to change is a valid no-op (RFC 5789 friendly). */
    public function test_passes_with_empty_payload(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    /** Any single allowed field can be sent in isolation. */
    public function test_passes_with_single_field_subset(): void
    {
        $validator = $this->validate(['title' => 'Renamed']);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    /** Multiple fields can be sent together; absent fields are not required. */
    public function test_passes_with_multi_field_subset(): void
    {
        $validator = $this->validate(['title' => 'X', 'price_usd' => 9.99]);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        return Validator::make($payload, (new UpdateBookRequest())->rules());
    }

    /**
     * `sometimes` makes the rule optional, but if the field IS present
     * the rest of the constraints still apply — same protections as on
     * create.
     */
    #[DataProvider('invalidPartialPayloadProvider')]
    public function test_provided_field_still_validates(string $field, mixed $value): void
    {
        $validator = $this->validate([$field => $value]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidPartialPayloadProvider(): array
    {
        return [
            'title too long' => ['title', str_repeat('x', 256)],
            'genre too long' => ['genre', str_repeat('y', 101)],
            'publication_date invalid' => ['publication_date', 'not-a-date'],
            'publication_date future' => ['publication_date', '2099-01-01'],
            'word_count zero' => ['word_count', 0],
            'word_count negative' => ['word_count', -5],
            'price negative' => ['price_usd', -1],
            'price too many decimals' => ['price_usd', 19.999],
        ];
    }

    /**
     * A PATCH that supplies only `title` must NOT trigger required errors
     * for publisher/author/etc. — `sometimes` short-circuits before the
     * `required` rule for fields not present in the payload.
     */
    public function test_does_not_require_other_fields_when_one_is_provided(): void
    {
        $validator = $this->validate(['title' => 'Updated']);

        $errors = $validator->errors()->toArray();

        $this->assertArrayNotHasKey('publisher', $errors);
        $this->assertArrayNotHasKey('author', $errors);
        $this->assertArrayNotHasKey('genre', $errors);
        $this->assertArrayNotHasKey('publication_date', $errors);
        $this->assertArrayNotHasKey('word_count', $errors);
        $this->assertArrayNotHasKey('price_usd', $errors);
    }
}
