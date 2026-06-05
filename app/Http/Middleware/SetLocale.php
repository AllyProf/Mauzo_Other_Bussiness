<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private LocaleService $locales)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->apply($request->user());

        return $next($request);
    }
}
