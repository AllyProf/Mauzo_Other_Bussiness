<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\App;

class LocaleService
{
    public function supported(): array
    {
        return config('locale.supported', ['en' => 'English']);
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, $this->supported());
    }

    public function normalize(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return $this->isSupported($locale) ? $locale : $this->default();
    }

    public function default(): string
    {
        return $this->normalize(config('locale.default', 'en'));
    }

    public function current(): string
    {
        return $this->normalize(App::getLocale());
    }

    public function resolve(?User $user = null): string
    {
        $user ??= auth()->user();

        if ($user && filled($user->locale)) {
            return $this->normalize($user->locale);
        }

        if ($locale = session('locale')) {
            return $this->normalize($locale);
        }

        if ($locale = request()->cookie('locale')) {
            return $this->normalize($locale);
        }

        return $this->default();
    }

    public function apply(?User $user = null): string
    {
        $locale = $this->resolve($user);
        App::setLocale($locale);

        return $locale;
    }

    public function set(string $locale, ?User $user = null): string
    {
        $locale = $this->normalize($locale);

        session(['locale' => $locale]);
        cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));

        if ($user) {
            $user->update(['locale' => $locale]);
        }

        App::setLocale($locale);

        return $locale;
    }
}
