<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * 各コントローラに認可と入力検証の共通機能を提供する。
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
