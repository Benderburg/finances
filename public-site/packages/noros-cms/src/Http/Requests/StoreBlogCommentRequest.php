<?php

namespace Noros\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Noros\Cms\Support\EngagementService;

class StoreBlogCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'min:2', 'max:100'],
            'author_email' => ['required', 'email:rfc', 'max:190'],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'consent' => ['accepted'],
            'company' => ['nullable', 'string', 'max:0'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(EngagementService::class)->validationMessages();
    }
}
