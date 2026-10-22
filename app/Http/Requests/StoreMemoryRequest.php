<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMemoryRequest extends FormRequest
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
        $sourceType = $this->input('source_type', 'text');

        $fileRules = match ($sourceType) {
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'document' => ['required', 'file', 'mimes:pdf,txt,doc,docx,md,csv', 'max:15360'],
            'voice' => ['required', 'file', 'mimes:mp3,m4a,wav,ogg,webm', 'max:25600'],
            default => ['nullable', 'file', 'max:25600'],
        };

        return [
            'source_type' => ['required', Rule::in(['text', 'image', 'document', 'voice', 'link'])],
            'title' => ['nullable', 'string', 'max:220'],
            'body' => ['nullable', 'string', 'max:20000'],
            'source_url' => ['nullable', 'required_if:source_type,link', 'url:http,https', 'max:2048'],
            'event_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'string', 'max:300'],
            'collection_id' => [
                'nullable',
                'integer',
                Rule::exists('collections', 'id')->where('user_id', $this->user()->id),
            ],
            'language' => ['nullable', Rule::in(['en', 'bn'])],
            'file' => $fileRules,
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $sourceType = $this->input('source_type');
                $file = $this->file('file');

                if (in_array($sourceType, ['image', 'document', 'voice'], true) && ! $file instanceof UploadedFile) {
                    $validator->errors()->add('file', __('ui.capture.upload_error'));
                }

                if (in_array($sourceType, ['text', 'link'], true)
                    && trim((string) $this->input('title')) === ''
                    && trim((string) $this->input('body')) === '') {
                    $validator->errors()->add('body', __('ui.capture.note_body'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_url.required_if' => __('ui.capture.link_url'),
            'file.uploaded' => __('ui.capture.upload_error'),
            'file.max' => __('ui.capture.upload_error'),
            'file.mimes' => __('ui.capture.upload_error'),
            'file.image' => __('ui.capture.upload_error'),
        ];
    }
}
