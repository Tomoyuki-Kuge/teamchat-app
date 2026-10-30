<?php

namespace App\Exceptions;

use App\Models\Message;
use RuntimeException;

class MessageConflict extends RuntimeException
{
    public function __construct(public readonly Message $current)
    {
        parent::__construct('別の画面で編集されています 内容を確認して保存してください');
    }
}
