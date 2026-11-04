<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('members.destroy')) {
            Gate::authorize('removeMember', [$this->route('channel'), (int) $this->route('user')]);
        } else {
            Gate::authorize('manageMembers', $this->route('channel'));
        }

        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string']] + ($this->routeIs('members.store')
            ? ['user_id' => ['bail', 'required', 'integer', 'exists:users,id']] : []);
    }

    public function messages(): array
    {
        return [
            'q.string' => '検索キーワードを正しい形式で入力してください',
            'user_id.required' => '追加するメンバーを選択してください',
            'user_id.integer' => '追加するメンバーを選択してください',
            'user_id.exists' => '追加するメンバーを選択してください',
        ];
    }
}
