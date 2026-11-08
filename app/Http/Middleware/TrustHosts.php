<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

/**
 * 信頼するホスト名のパターンを定義する。
 */
class TrustHosts extends Middleware
{
    /**
     * アプリのURLを基に信頼するホストのパターンを返す。
     *
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }
}
