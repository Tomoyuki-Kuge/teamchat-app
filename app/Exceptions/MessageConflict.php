<?php

namespace App\Exceptions;

use App\Models\Message;
use RuntimeException;

/**
 * メッセージ本文の編集競合を通知する例外。
 */
class MessageConflict extends RuntimeException
{
    /**
     * 競合検知時の対象メッセージを保持し、案内文を設定する。
     */
    public function __construct(public readonly Message $current)
    {
        parent::__construct('別の画面で編集されています 内容を確認して保存してください');
    }
}
