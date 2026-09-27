<?php

namespace App\Http\Requests\Admin;

use App\Models\RecommendationAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('condition_metadata');

        $this->merge([
            'condition_metadata' => is_string($metadata) && trim($metadata) === '' ? null : $metadata,
            'is_active' => $this->boolean('is_active'),
            'approval_confirmed' => $this->boolean('approval_confirmed'),
        ]);
    }

    public function rules(): array
    {
        $action = $this->route('recommendationAction');

        return [
            'title' => [
                'required',
                'string',
                'max:140',
                Rule::unique('recommendation_actions', 'title')->ignore($action instanceof RecommendationAction ? $action->id : null),
            ],
            'description' => ['required', 'string', 'max:2000'],
            'condition_metadata' => ['nullable', 'json'],
            'is_active' => ['boolean'],
            'approval_confirmed' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('is_active') && ! $this->boolean('approval_confirmed')) {
                $validator->errors()->add(
                    'approval_confirmed',
                    'Confirm that this recommendation action wording has been reviewed and approved before activating it.'
                );
            }
        });
    }
}
