<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard sends `Accept-Language: ar` or `en` on every request (set
 * once when the user flips the language switch). This drives which locale
 * Laravel's validation messages, notifications, etc. render in — it does
 * NOT translate stored data; product/customer content is stored as entered.
 */
class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', config('app.locale'));
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';

        app()->setLocale($locale);

        return $next($request);
    }
}
