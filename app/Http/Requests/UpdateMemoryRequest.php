<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $memory = $this->route('memory');

        return $memory instanceof Memory && $this->user()?->can('update', $memory);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:220'],
            'memory_type' => ['required', Rule::in([
                'purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair',
                'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link',
            ])],
            'body' => ['nullable', 'string', 'max:50000'],
            'extracted_text' => ['nullable', 'string', 'max:200000'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['nullable', 'date'],
            'detected_language' => ['required', Rule::in(['en', 'bn'])],
            'tags' => ['nullable', 'string', 'max:300'],
            'collection_ids' => ['nullable', 'array', 'max:20'],
            'collection_ids.*' => [
                'integer',
                Rule::exists('collections', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
