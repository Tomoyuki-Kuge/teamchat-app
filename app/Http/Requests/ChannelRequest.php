<?php

namespace App\Http\Requests;

use App\Support\AuthInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 入力不備より先に、存在を明かせる対象か・管理できるかを確認します。
        if (! $this->routeIs('channels.store')) {
            Gate::authorize($this->routeIs('channels.destroy') ? 'delete' : 'update', $this->route('channel'));
        }

        return true;
    }

    public function rules(): array
    {
        $notBlank = function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && AuthInput::trim($value) === '') {
                $fail($this->messages()[$attribute.'.required']);
            }
        };

        if ($this->routeIs('channels.destroy')) {
            return ['confirmation_name' => ['bail', 'required', 'string', $notBlank,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== $this->route('channel')->name) {
                        $fail('チャンネル名が一致しません');
                    }
                }]];
        }

        $unique = Rule::unique('channels', 'name')->whereNull('deleted_at');
        if ($this->routeIs('channels.update')) {
            $unique->ignore($this->route('channel')->id);
        }
        $rules = [
            'name' => ['bail', 'required', 'string', $notBlank, 'max:50', $unique],
            'description' => ['nullable', 'string', 'max:200'],
        ];
        if ($this->routeIs('channels.store')) {
            $rules['is_private'] = ['bail', 'required', Rule::in(['0', '1'])];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'チャンネル名を入力してください',
            'name.string' => 'チャンネル名を入力してください',
            'name.max' => 'チャンネル名は50文字以内で入力してください',
            'name.unique' => '使用できない名前です',
            'description.string' => '説明は文字列で入力してください',
            'description.max' => '説明は200文字以内で入力してください',
            'is_private.required' => '公開範囲を選択してください',
            'is_private.in' => '公開範囲を選択してください',
            'confirmation_name.required' => '確認のためチャンネル名を入力してください',
            'confirmation_name.string' => '確認のためチャンネル名を入力してください',
        ];
    }
}
