<?php

namespace App\Http\Requests\Knowledge;

use App\Rules\BlockNoteDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgePageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'array', 'min:1', new BlockNoteDocument],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the validated changes, keeping only the fields the editor sent.
     *
     * @return array{title?: string|null, content?: array<mixed>|null, version: int}
     */
    public function changes(): array
    {
        $validated = $this->validated();

        $changes = ['version' => (int) $validated['version']];

        if (array_key_exists('title', $validated)) {
            $changes['title'] = is_string($validated['title']) ? $validated['title'] : null;
        }

        if (array_key_exists('content', $validated)) {
            $changes['content'] = is_array($validated['content']) ? $validated['content'] : null;
        }

        return $changes;
    }
}
