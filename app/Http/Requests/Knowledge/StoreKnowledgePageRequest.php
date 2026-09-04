<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgePageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('knowledge_pages', 'id')->where('team_id', $this->team()->id),
            ],
        ];
    }

    /**
     * Get the team the page is added to.
     */
    private function team(): Team
    {
        $team = $this->route('current_team');

        abort_if(! $team instanceof Team, 404);

        return $team;
    }
}
