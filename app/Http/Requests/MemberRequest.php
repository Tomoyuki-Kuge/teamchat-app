<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * メンバーの追加・解除に必要な認可と入力検証を担う。
 */
class MemberRequest extends FormRequest
{
    /**
     * メンバーの追加・解除に必要な権限を確認する。
     */
    public function authorize(): bool
    {
        if ($this->routeIs('members.destroy')) {
            Gate::authorize('removeMember', [$this->route('channel'), (int) $this->route('user')]);
        } else {
            Gate::authorize('manageMembers', $this->route('channel'));
        }

        return true;
    }

    /**
     * 候補の検索語と追加対象の入力ルールを返す。
     */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string']] + ($this->routeIs('members.store')
            ? ['user_id' => ['bail', 'required', 'integer', 'exists:users,id']] : []);
    }

    /**
     * メンバー管理の入力エラーメッセージを返す。
     */
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
