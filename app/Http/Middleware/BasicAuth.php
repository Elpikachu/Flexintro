<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The demo URL is public, so the whole app sits behind HTTP basic auth.
 * `/up` is registered outside the web group and stays open, so the presenter
 * can check the app from a phone.
 */
class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = config('demo.basic_auth.user');
        $password = config('demo.basic_auth.password');

        // Not configured (local development): let everything through.
        if ($user === null || $password === null || $user === '' || $password === '') {
            return $next($request);
        }

        $givenUser = (string) $request->getUser();
        $givenPassword = (string) $request->getPassword();

        if (hash_equals($user, $givenUser) && hash_equals($password, $givenPassword)) {
            return $next($request);
        }

        return response('Authentication required.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Flex demo", charset="UTF-8"',
        ]);
    }
}
