<?php

namespace App\Http\Requests;

use App\Models\Message;
use App\Support\AuthInput;
use App\Support\Messages\ConversationRange;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MessageRequest extends FormRequest
{
    public ?Message $target = null;

    public ?ConversationRange $range = null;

    public function authorize(): bool
    {
        $channel = $this->route('channel');
        Gate::authorize('view', $channel);
        if (! $this->routeIs('messages.store')) {
            $this->target = $channel->messages()->whereNull('parent_message_id')->findOrFail($this->route('message'));
            Gate::authorize($this->routeIs('messages.destroy') ? 'delete' : 'update', $this->target);
            abort_if($this->target->deleted_at !== null, 409, 'このメッセージは削除されています');
        }
        try {
            $this->range = ConversationRange::from($channel, $this->only('messages_until', 'messages_pages', 'origin'));
        } catch (ValidationException $exception) {
            $this->reject($exception->errors());
        }
        if ($this->routeIs('messages.update') && ! $this->range->messages->contains('id', $this->target->id)) {
            $this->reject(['edit' => ['表示条件が正しくありません']]);
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->routeIs('messages.destroy')) {
            return [];
        }
        $rules = ['body' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && AuthInput::trim($value) === '') {
                $fail('メッセージを入力してください');
            }
        }, 'max:1000']];
        if ($this->routeIs('messages.update')) {
            $rules['expected_version'] = ['bail', 'required', 'integer', 'min:1', 'max:4294967295', 'regex:/^[0-9]+$/D'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'body.required' => 'メッセージを入力してください',
            'body.string' => 'メッセージを入力してください',
            'body.max' => 'メッセージは1,000文字以内で入力してください',
            'expected_version.*' => '編集状態が正しくありません 画面を開き直してください',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject($validator->errors()->toArray());
    }

    private function reject(array $errors): never
    {
        $edit = $this->routeIs('messages.update') && $this->range?->messages->contains('id', $this->target->id);
        $url = $this->range?->url($edit ? ['edit' => $this->target->id] : [], $edit ? $this->target->id : null)
            ?? route('channels.show', $this->route('channel'));
        throw new HttpResponseException(redirect()->to($url, 303)->withErrors($errors)
            ->with('message_form', $this->route()->getName().':'.$this->route('channel')->id.':'.($this->target?->id ?? 'new'))
            ->withInput([
                'body' => is_string($this->input('body')) ? $this->input('body') : null,
                'expected_version' => is_string($this->input('expected_version')) || is_int($this->input('expected_version')) ? $this->input('expected_version') : null,
            ]));
    }
}
