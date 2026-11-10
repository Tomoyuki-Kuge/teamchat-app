<?php

namespace App\Http\Responses\Api;

use Illuminate\Http\JsonResponse;

/**
 * 公開APIのJSON形式とページリンクを組み立てる。
 */
class PublicApiResponse
{
    public static function json(array $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers);
    }

    public static function links(string $path, int $page, int $until, int $lastPage): array
    {
        $url = fn (int $number) => $path.'?page='.$number.'&until_id='.$until;

        return [
            'prev' => $page > 1 ? $url($page - 1) : null,
            'next' => $page < $lastPage ? $url($page + 1) : null,
        ];
    }

    public static function error(int $status, array $details = [], array $headers = []): JsonResponse
    {
        [$code, $message] = match ($status) {
            404 => ['NOT_FOUND', '対象が見つかりません'],
            405 => ['METHOD_NOT_ALLOWED', 'このHTTPメソッドは使用できません'],
            422 => ['VALIDATION_ERROR', '入力内容を確認してください'],
            default => ['INTERNAL_ERROR', '処理に失敗したため時間をおいて再度お試しください'],
        };

        return self::json(['error' => ['code' => $code, 'message' => $message, 'details' => (object) $details]], $status, $headers);
    }
}
