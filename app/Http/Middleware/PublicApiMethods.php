<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * CORSのプリフライトとルーターのOPTIONS自動応答より先にAPIのメソッドを確認する。
 */
class PublicApiMethods
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api', 'api/*')) {
            $method = $request->getRealMethod();
            // ルート照合とHEAD本文省略も実メソッドに揃え、webのフォーム上書きは維持します。
            $request->headers->remove('X-HTTP-Method-Override');
            $request->request->remove('_method');
            $request->query->remove('_method');
            $request->setMethod($method);

            if (! in_array($method, ['GET', 'HEAD'], true)) {
                $known = $request->is('api/channels') || preg_match('#^api/channels/[^/]+/messages$#D', $request->path());
                abort_unless($known, 404);
                throw new MethodNotAllowedHttpException(['GET', 'HEAD']);
            }
        }

        return $next($request);
    }
}
