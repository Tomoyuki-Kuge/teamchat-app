<?php

namespace App\Http\Requests\Api;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 公開対象を入力検証より先に確認し、ページ指定を検証する。
 */
class PublicApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('api.messages.index')) {
            $id = $this->route('channel');
            abort_unless(is_string($id) && preg_match('/^[0-9]+$/D', $id) && (int) $id > 0, 404);
            $channel = Channel::query()->active()->where('is_private', false)->findOrFail($id);
            $this->route()->setParameter('channel', $channel);
        }

        return true;
    }

    public function rules(): array
    {
        $integer = function (string $attribute, mixed $value, \Closure $fail) {
            $digits = ltrim((string) $value, '0');
            $maximum = (string) PHP_INT_MAX;
            if (($attribute === 'page' && $digits === '')
                || strlen($digits) > strlen($maximum)
                || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
                $fail($attribute === 'page' ? 'pageは1以上の整数で指定してください' : 'until_idは0以上の整数で指定してください');
            }
        };

        return [
            'page' => ['bail', 'sometimes', 'required', 'regex:/^[0-9]+$/D', $integer],
            'until_id' => ['bail', 'sometimes', 'required', 'regex:/^[0-9]+$/D', $integer],
        ];
    }

    public function validationData(): array
    {
        return $this->query->all();
    }

    public function messages(): array
    {
        return [
            'page.*' => 'pageは1以上の整数で指定してください',
            'until_id.*' => 'until_idは0以上の整数で指定してください',
        ];
    }
}
