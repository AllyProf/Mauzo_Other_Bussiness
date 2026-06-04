<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpGeolocationService
{
    /**
     * @return array{city: ?string, region: ?string, country: ?string, isp: ?string, label: string, is_local: bool}
     */
    public function lookup(?string $ip): array
    {
        $ip = trim((string) $ip);

        if ($ip === '' || $this->isPrivateIp($ip)) {
            return [
                'city' => null,
                'region' => null,
                'country' => null,
                'isp' => null,
                'label' => 'Local / private network',
                'is_local' => true,
            ];
        }

        return Cache::remember('ip_geo:'.md5($ip), now()->addDays(7), function () use ($ip) {
            return $this->fetch($ip);
        });
    }

    public function formatLabel(?string $ip): string
    {
        return $this->lookup($ip)['label'];
    }

    /**
     * @return array{city: ?string, region: ?string, country: ?string, isp: ?string, label: string, is_local: bool}
     */
    private function fetch(string $ip): array
    {
        $empty = [
            'city' => null,
            'region' => null,
            'country' => null,
            'isp' => null,
            'label' => 'Location unknown',
            'is_local' => false,
        ];

        try {
            $response = Http::timeout(4)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,country,regionName,city,isp',
                ]);

            if (! $response->successful()) {
                return $empty;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return $empty;
            }

            $city = $data['city'] ?? null;
            $region = $data['regionName'] ?? null;
            $country = $data['country'] ?? null;
            $isp = $data['isp'] ?? null;

            $parts = array_filter([$city, $region, $country]);
            $label = $parts !== [] ? implode(', ', $parts) : 'Location unknown';
            if ($isp) {
                $label .= ' · '.$isp;
            }

            return [
                'city' => $city,
                'region' => $region,
                'country' => $country,
                'isp' => $isp,
                'label' => $label,
                'is_local' => false,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
