<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGenerationRequest extends FormRequest
{
    public const SIZES = ['1024x1024', '1536x1024', '1024x1536'];

    public const QUALITIES = ['low', 'medium', 'high'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'in:'.implode(',', self::SIZES)],
            'quality' => ['required', 'in:'.implode(',', self::QUALITIES)],
            'reference_images' => ['array', 'max:4'],
            'reference_images.*' => ['url:http,https', 'max:2048'],
        ];
    }
}
