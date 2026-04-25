<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ポートフォリオ名',
            'description' => '説明',
        ];
    }
}
