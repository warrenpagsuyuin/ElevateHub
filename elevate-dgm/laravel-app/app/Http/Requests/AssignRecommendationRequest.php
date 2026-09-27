<?php

namespace App\Http\Requests;

use App\Models\RecommendationAction;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $satellite = $this->route('satellite');

        return $user instanceof User
            && $satellite instanceof Satellite
            && $user->canManageSatellite($satellite);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'assignment_notes' => trim((string) $this->input('assignment_notes', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'recommended_action_id' => ['required', 'integer', 'exists:recommendation_actions,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'assignment_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = RecommendationAction::find($this->integer('recommended_action_id'));

            $member = $this->route('member');
            $result = $member instanceof Member ? $member->engagementResult : null;

            if ($action !== null && (! $action->is_active || ! $action->approved_by || ! $action->approved_at)) {
                $validator->errors()->add('recommended_action_id', 'Only active catalogue actions with a recorded approver and approval date can be assigned.');
            } elseif ($action !== null && $result !== null && ! $action->supportsResult($result)) {
                $validator->errors()->add('recommended_action_id', 'This action does not match the result. Insufficient information permits only an approved data-validation action.');
            }

            $satellite = $this->route('satellite');
            $assignee = User::find($this->integer('assigned_to'));

            if (! $satellite instanceof Satellite || ! $assignee instanceof User) {
                return;
            }

            if (! $assignee->canAccessSatellite($satellite)) {
                $validator->errors()->add('assigned_to', 'Assign the recommendation to a user in this satellite district.');
            }
        });
    }
}
