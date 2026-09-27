<?php

namespace App\Http\Requests;

use App\Models\Recommendation;
use App\Models\Satellite;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $satellite = $this->route('satellite');
        $recommendation = $this->route('recommendation');

        if (! $user instanceof User || ! $satellite instanceof Satellite || ! $recommendation instanceof Recommendation) {
            return false;
        }

        if ((int) $recommendation->member?->satellite_id !== (int) $satellite->id) {
            return false;
        }

        if (! $user->canAccessSatellite($satellite)) {
            return false;
        }

        return $user->canManageSatellite($satellite)
            || (int) $recommendation->assigned_to === (int) $user->id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_notes' => trim((string) $this->input('review_notes', '')),
            'modified_action_description' => trim((string) $this->input('modified_action_description', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Recommendation::REVIEW_STATUSES)],
            'review_notes' => ['nullable', 'string', 'max:3000'],
            'modified_action_description' => ['required_if:status,Modified', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $recommendation = $this->route('recommendation');

            if (! $recommendation instanceof Recommendation) {
                return;
            }

            if (! $recommendation->is_reviewable) {
                $validator->errors()->add('status', 'Only pending or modified, unexpired recommendations can be reviewed.');
            }

            $status = (string) $this->input('status');
            $requiresNotes = in_array($status, [Recommendation::STATUS_MODIFIED, Recommendation::STATUS_REJECTED], true);

            if ($requiresNotes && trim((string) $this->input('review_notes')) === '') {
                $validator->errors()->add('review_notes', 'Review notes are required when modifying or rejecting a recommendation.');
            }
        });
    }

}
