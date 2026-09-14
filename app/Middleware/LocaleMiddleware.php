<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Services\LocalizationService;

class LocaleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        $localization = new LocalizationService();

        $locale = $localization->preferredLocale(
            $request->header('x-locale'),
            $request->header('accept-language'),
            $request->getUser()
        );

        Request::setLocale($locale);

        $next($request);
    }
}