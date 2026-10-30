<?php

namespace App\Exceptions;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    // 入力エラーで画面へ戻す際も、パスワードはセッションに残しません。
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    // 障害のログと画面に、パスワードやSQLなどの内部情報を出さないようにします。
    public function register(): void
    {
        $this->reportable(function (Throwable $exception) {
            $context = [
                'exception_class' => $exception::class,
                'route' => request()->route()?->getName(),
            ];
            $channel = request()->route('channel');
            if ($channel instanceof Channel) {
                $context['channel_id'] = $channel->id;
            }
            $message = request()->route('message');
            if ($message instanceof Message || (is_string($message) && ctype_digit($message))) {
                $context['message_id'] = $message instanceof Message ? $message->id : (int) $message;
            }
            Log::error('Application operation failed', $context);

            // 秘密情報を含み得る標準ログへの出力を、ここで止めます。
            return false;
        });

        $this->renderable(function (Throwable $exception) {
            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof HttpResponseException) {
                return null;
            }

            // CSRF不一致のフォームは再送せずログインへ戻し、JSON要求は標準の419を返します。
            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 419) {
                return request()->expectsJson() ? null : redirect()->route('login');
            }

            if (! $exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
                return response()->view('errors.500', [], 500);
            }

            return null;
        });
    }

    // 入力エラー時は操作ごとのフォームへ戻し、許可した入力だけを再表示します。
    protected function invalid($request, ValidationException $exception)
    {
        if ($request->routeIs('channels.store', 'channels.update', 'channels.destroy')) {
            $route = $request->route()->getName();
            $channel = $request->route('channel');
            $parameters = $channel ? ['channel' => $channel->id] : [];
            if ($route === 'channels.destroy') {
                $parameters['confirm_delete'] = 1;
            }
            $target = $route === 'channels.store' ? 'channels.create' : 'channels.edit';

            return redirect()->route($target, $parameters, 303)
                ->withErrors($exception->errors())
                ->with('channel_form', $route.($channel ? ':'.$channel->id : ''))
                // 空入力のNULLは残し、編集前の値が再表示されるのを防ぎます。
                ->withInput(array_filter(
                    $request->only('name', 'description', 'is_private', 'confirmation_name'),
                    fn ($value) => is_string($value) || $value === null,
                ));
        }

        if (! $request->routeIs('login.store', 'register.store')) {
            return parent::invalid($request, $exception);
        }

        $errors = $exception->errors();
        // Fortifyの認証失敗メッセージを、画面設計に合わせてパスワード欄に表示します。
        if ($request->routeIs('login.store') && ($errors['email'] ?? []) === [trans('auth.failed')]) {
            $errors = ['password' => $errors['email']];
        }

        return redirect()->route($request->routeIs('register.store') ? 'register' : 'login', [], 303)
            ->withErrors($errors)
            // 配列などを入力欄へ戻して型エラーにしないよう、文字列だけを保持します。
            ->withInput(array_filter($request->only('name', 'email'), 'is_string'));
    }

    // Laravel標準に任せ、JSON要求には401、通常の画面にはログインへの302を返します。
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return parent::unauthenticated($request, $exception);
    }
}
