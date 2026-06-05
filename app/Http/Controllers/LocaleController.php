<?php

namespace App\Http\Controllers;

use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale, LocaleService $locales): RedirectResponse
    {
        if (! $locales->isSupported($locale)) {
            abort(404);
        }

        $locales->set($locale, $request->user());

        return redirect()->back(fallback: route('landing.index'));
    }
}
