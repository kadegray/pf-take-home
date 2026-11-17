<?php

namespace App\Http\Requests;

use App\Services\Pixelforge\Pricing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'string', Rule::in(Pricing::sizes())],
            'quality' => ['required', 'string', Rule::in(Pricing::qualities())],
            'reference_images' => ['nullable', 'array', 'max:'.Pricing::maxReferenceImages()],
            'reference_images.*' => ['url'],
        ];
    }
}
