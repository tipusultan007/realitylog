<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'due_at' => ['required', 'date', 'after:now'],
            'type' => ['required', Rule::in(['custom', 'warranty', 'renewal', 'follow_up', 'maintenance', 'event'])],
            'memory_id' => [
                'nullable',
                'integer',
                Rule::exists('memories', 'id')->where('user_id', $this->user()->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'notification_channel' => ['nullable', Rule::in(['in_app', 'email'])],
        ];
    }
}
