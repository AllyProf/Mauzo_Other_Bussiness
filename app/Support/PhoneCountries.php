<?php

namespace App\Support;

use Illuminate\Http\Request;

class PhoneCountries
{
    public const DEFAULT_ISO = 'tz';

    private const SHARED_DIAL_PREFERENCE = ['1' => 'us', '7' => 'ru'];

    /** @var array<string, array{0: string, 1: string}> iso => [name, dial code] */
    private const COUNTRIES = [
        'tz' => ['Tanzania', '255'], 'ke' => ['Kenya', '254'], 'ug' => ['Uganda', '256'], 'rw' => ['Rwanda', '250'],
        'bi' => ['Burundi', '257'], 'cd' => ['DR Congo', '243'], 'zm' => ['Zambia', '260'], 'mw' => ['Malawi', '265'],
        'mz' => ['Mozambique', '258'], 'ss' => ['South Sudan', '211'], 'et' => ['Ethiopia', '251'], 'so' => ['Somalia', '252'],
        'af' => ['Afghanistan', '93'], 'al' => ['Albania', '355'], 'dz' => ['Algeria', '213'], 'ad' => ['Andorra', '376'],
        'ao' => ['Angola', '244'], 'ag' => ['Antigua and Barbuda', '1268'], 'ar' => ['Argentina', '54'], 'am' => ['Armenia', '374'],
        'au' => ['Australia', '61'], 'at' => ['Austria', '43'], 'az' => ['Azerbaijan', '994'], 'bs' => ['Bahamas', '1242'],
        'bh' => ['Bahrain', '973'], 'bd' => ['Bangladesh', '880'], 'bb' => ['Barbados', '1246'], 'by' => ['Belarus', '375'],
        'be' => ['Belgium', '32'], 'bz' => ['Belize', '501'], 'bj' => ['Benin', '229'], 'bt' => ['Bhutan', '975'],
        'bo' => ['Bolivia', '591'], 'ba' => ['Bosnia and Herzegovina', '387'], 'bw' => ['Botswana', '267'], 'br' => ['Brazil', '55'],
        'bn' => ['Brunei', '673'], 'bg' => ['Bulgaria', '359'], 'bf' => ['Burkina Faso', '226'], 'kh' => ['Cambodia', '855'],
        'cm' => ['Cameroon', '237'], 'ca' => ['Canada', '1'], 'cv' => ['Cape Verde', '238'], 'cf' => ['Central African Republic', '236'],
        'td' => ['Chad', '235'], 'cl' => ['Chile', '56'], 'cn' => ['China', '86'], 'co' => ['Colombia', '57'],
        'km' => ['Comoros', '269'], 'cg' => ['Congo', '242'], 'cr' => ['Costa Rica', '506'], 'ci' => ["Côte d'Ivoire", '225'],
        'hr' => ['Croatia', '385'], 'cu' => ['Cuba', '53'], 'cy' => ['Cyprus', '357'], 'cz' => ['Czechia', '420'],
        'dk' => ['Denmark', '45'], 'dj' => ['Djibouti', '253'], 'dm' => ['Dominica', '1767'], 'do' => ['Dominican Republic', '1809'],
        'ec' => ['Ecuador', '593'], 'eg' => ['Egypt', '20'], 'sv' => ['El Salvador', '503'], 'gq' => ['Equatorial Guinea', '240'],
        'er' => ['Eritrea', '291'], 'ee' => ['Estonia', '372'], 'sz' => ['Eswatini', '268'], 'fj' => ['Fiji', '679'],
        'fi' => ['Finland', '358'], 'fr' => ['France', '33'], 'ga' => ['Gabon', '241'], 'gm' => ['Gambia', '220'],
        'ge' => ['Georgia', '995'], 'de' => ['Germany', '49'], 'gh' => ['Ghana', '233'], 'gr' => ['Greece', '30'],
        'gd' => ['Grenada', '1473'], 'gt' => ['Guatemala', '502'], 'gn' => ['Guinea', '224'], 'gw' => ['Guinea-Bissau', '245'],
        'gy' => ['Guyana', '592'], 'ht' => ['Haiti', '509'], 'hn' => ['Honduras', '504'], 'hk' => ['Hong Kong', '852'],
        'hu' => ['Hungary', '36'], 'is' => ['Iceland', '354'], 'in' => ['India', '91'], 'id' => ['Indonesia', '62'],
        'ir' => ['Iran', '98'], 'iq' => ['Iraq', '964'], 'ie' => ['Ireland', '353'], 'il' => ['Israel', '972'],
        'it' => ['Italy', '39'], 'jm' => ['Jamaica', '1876'], 'jp' => ['Japan', '81'], 'jo' => ['Jordan', '962'],
        'kz' => ['Kazakhstan', '7'], 'ki' => ['Kiribati', '686'], 'kw' => ['Kuwait', '965'], 'kg' => ['Kyrgyzstan', '996'],
        'la' => ['Laos', '856'], 'lv' => ['Latvia', '371'], 'lb' => ['Lebanon', '961'], 'ls' => ['Lesotho', '266'],
        'lr' => ['Liberia', '231'], 'ly' => ['Libya', '218'], 'li' => ['Liechtenstein', '423'], 'lt' => ['Lithuania', '370'],
        'lu' => ['Luxembourg', '352'], 'mo' => ['Macau', '853'], 'mg' => ['Madagascar', '261'], 'my' => ['Malaysia', '60'],
        'mv' => ['Maldives', '960'], 'ml' => ['Mali', '223'], 'mt' => ['Malta', '356'], 'mh' => ['Marshall Islands', '692'],
        'mr' => ['Mauritania', '222'], 'mu' => ['Mauritius', '230'], 'mx' => ['Mexico', '52'], 'fm' => ['Micronesia', '691'],
        'md' => ['Moldova', '373'], 'mc' => ['Monaco', '377'], 'mn' => ['Mongolia', '976'], 'me' => ['Montenegro', '382'],
        'ma' => ['Morocco', '212'], 'mm' => ['Myanmar', '95'], 'na' => ['Namibia', '264'], 'nr' => ['Nauru', '674'],
        'np' => ['Nepal', '977'], 'nl' => ['Netherlands', '31'], 'nz' => ['New Zealand', '64'], 'ni' => ['Nicaragua', '505'],
        'ne' => ['Niger', '227'], 'ng' => ['Nigeria', '234'], 'kp' => ['North Korea', '850'], 'mk' => ['North Macedonia', '389'],
        'no' => ['Norway', '47'], 'om' => ['Oman', '968'], 'pk' => ['Pakistan', '92'], 'pw' => ['Palau', '680'],
        'ps' => ['Palestine', '970'], 'pa' => ['Panama', '507'], 'pg' => ['Papua New Guinea', '675'], 'py' => ['Paraguay', '595'],
        'pe' => ['Peru', '51'], 'ph' => ['Philippines', '63'], 'pl' => ['Poland', '48'], 'pt' => ['Portugal', '351'],
        'qa' => ['Qatar', '974'], 'ro' => ['Romania', '40'], 'ru' => ['Russia', '7'], 'kn' => ['Saint Kitts and Nevis', '1869'],
        'lc' => ['Saint Lucia', '1758'], 'vc' => ['Saint Vincent and the Grenadines', '1784'], 'ws' => ['Samoa', '685'],
        'sm' => ['San Marino', '378'], 'st' => ['São Tomé and Príncipe', '239'], 'sa' => ['Saudi Arabia', '966'],
        'sn' => ['Senegal', '221'], 'rs' => ['Serbia', '381'], 'sc' => ['Seychelles', '248'], 'sl' => ['Sierra Leone', '232'],
        'sg' => ['Singapore', '65'], 'sk' => ['Slovakia', '421'], 'si' => ['Slovenia', '386'], 'sb' => ['Solomon Islands', '677'],
        'za' => ['South Africa', '27'], 'kr' => ['South Korea', '82'], 'es' => ['Spain', '34'], 'lk' => ['Sri Lanka', '94'],
        'sd' => ['Sudan', '249'], 'sr' => ['Suriname', '597'], 'se' => ['Sweden', '46'], 'ch' => ['Switzerland', '41'],
        'sy' => ['Syria', '963'], 'tw' => ['Taiwan', '886'], 'tj' => ['Tajikistan', '992'], 'th' => ['Thailand', '66'],
        'tl' => ['Timor-Leste', '670'], 'tg' => ['Togo', '228'], 'to' => ['Tonga', '676'], 'tt' => ['Trinidad and Tobago', '1868'],
        'tn' => ['Tunisia', '216'], 'tr' => ['Turkey', '90'], 'tm' => ['Turkmenistan', '993'], 'tv' => ['Tuvalu', '688'],
        'ae' => ['United Arab Emirates', '971'], 'gb' => ['United Kingdom', '44'], 'us' => ['United States', '1'],
        'uy' => ['Uruguay', '598'], 'uz' => ['Uzbekistan', '998'], 'vu' => ['Vanuatu', '678'], 'va' => ['Vatican City', '379'],
        've' => ['Venezuela', '58'], 'vn' => ['Vietnam', '84'], 'ye' => ['Yemen', '967'], 'zw' => ['Zimbabwe', '263'],
    ];

    /** @return array<int, array{iso: string, name: string, dial: string}> */
    public static function all(): array
    {
        $list = [];
        foreach (self::COUNTRIES as $iso => [$name, $dial]) {
            $list[] = ['iso' => $iso, 'name' => $name, 'dial' => $dial];
        }

        return $list;
    }

    public static function isValidIso(?string $iso): bool
    {
        return $iso !== null && isset(self::COUNTRIES[strtolower($iso)]);
    }

    public static function dialFor(?string $iso): string
    {
        $iso = strtolower((string) $iso);

        return self::COUNTRIES[$iso][1] ?? self::COUNTRIES[self::DEFAULT_ISO][1];
    }

    /** Build an E.164-style number (e.g. +255712345678) from a country and the local digits. */
    public static function combine(?string $iso, ?string $local): string
    {
        $dial = self::dialFor($iso);
        $digits = preg_replace('/\D+/', '', (string) $local);

        if ($digits !== '' && str_starts_with($digits, $dial) && strlen($digits) > strlen($dial) + 6) {
            $digits = substr($digits, strlen($dial));
        }

        return '+'.$dial.ltrim($digits, '0');
    }

    /**
     * Read "{field}" + "{field}_country" (or the given country field) from a request.
     * Requests without a country (e.g. mobile API) fall back to Tanzania, matching the old "+255" behaviour.
     */
    public static function fromRequest(Request $request, string $field = 'phone', ?string $countryField = null): ?string
    {
        $local = trim((string) $request->input($field, ''));
        if ($local === '') {
            return null;
        }

        if (str_starts_with($local, '+')) {
            return '+'.preg_replace('/\D+/', '', $local);
        }

        return self::combine($request->input($countryField ?? self::countryFieldFor($field)), $local);
    }

    public static function countryFieldFor(string $field): string
    {
        return $field === 'phone' ? 'phone_country' : $field.'_country';
    }

    /** Validation rules for a local-number field paired with its country field. */
    public static function rules(bool $required = false, ?string $countryField = null, string $field = 'phone', bool $tzMobileOnly = true): array
    {
        $countryField ??= self::countryFieldFor($field);

        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            function ($attribute, $value, $fail) use ($countryField, $tzMobileOnly) {
                if (blank($value)) {
                    return;
                }
                $iso = request()->input($countryField) ?: self::DEFAULT_ISO;
                if (! self::isValidIso($iso)) {
                    $fail('Select a valid country code.');

                    return;
                }
                if (! self::isValidLocal($iso, (string) $value, $tzMobileOnly)) {
                    $fail(strtolower($iso) === self::DEFAULT_ISO
                        ? ($tzMobileOnly
                            ? 'Enter a valid Tanzanian number: 9 digits starting with 6, 7 or 8 (e.g. 712345678).'
                            : 'Enter a valid Tanzanian number: 9 digits without the leading 0 (e.g. 712345678).')
                        : 'Enter a valid phone number for the selected country.');
                }
            },
        ];
    }

    public static function isValidLocal(string $iso, string $local, bool $tzMobileOnly = true): bool
    {
        if (str_starts_with(trim($local), '+')) {
            $digits = preg_replace('/\D+/', '', $local);

            return strlen($digits) >= 7 && strlen($digits) <= 15;
        }

        $digits = ltrim(preg_replace('/\D+/', '', $local), '0');
        $dial = self::dialFor($iso);
        if (str_starts_with($digits, $dial) && strlen($digits) > strlen($dial) + 6) {
            $digits = substr($digits, strlen($dial));
        }

        if (strtolower($iso) === self::DEFAULT_ISO) {
            return (bool) preg_match($tzMobileOnly ? '/^[678]\d{8}$/' : '/^[2-9]\d{8}$/', $digits);
        }

        return strlen($digits) >= 4 && strlen($digits) <= 14;
    }

    /** @return array{iso: string, local: string} */
    public static function split(?string $phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return ['iso' => self::DEFAULT_ISO, 'local' => ''];
        }

        if (str_starts_with($digits, self::COUNTRIES[self::DEFAULT_ISO][1])) {
            return ['iso' => self::DEFAULT_ISO, 'local' => substr($digits, strlen(self::COUNTRIES[self::DEFAULT_ISO][1]))];
        }

        $match = null;
        foreach (self::COUNTRIES as $iso => [, $dial]) {
            if (str_starts_with($digits, $dial) && ($match === null || strlen($dial) > strlen(self::COUNTRIES[$match][1]))) {
                $match = $iso;
            }
        }

        if ($match === null) {
            return ['iso' => self::DEFAULT_ISO, 'local' => ltrim($digits, '0')];
        }

        $match = self::SHARED_DIAL_PREFERENCE[self::COUNTRIES[$match][1]] ?? $match;

        return ['iso' => $match, 'local' => substr($digits, strlen(self::COUNTRIES[$match][1]))];
    }
}
