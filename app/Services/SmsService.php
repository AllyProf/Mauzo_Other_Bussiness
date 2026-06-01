<?php

namespace App\Services;

class SmsService
{
    private string $username = 'emcatechn';

    private string $password = 'Emca@#12';

    private string $from = 'MauzoLink';

    private string $baseUrl = 'https://messaging-service.co.tz/link/sms/v1/text/single';

    /**
     * @return array{success: bool, error?: string, response?: mixed, http_code?: int}
     */
    public function sendSms(string $phoneNumber, string $message): array
    {
        $phoneNo = $this->formatPhoneNumber($phoneNumber);
        $text = urlencode($message);
        $url = $this->baseUrl.'?username='.$this->username
            .'&password='.urlencode($this->password)
            .'&from='.$this->from
            .'&to='.$phoneNo
            .'&text='.$text;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: '.$error,
                'response' => null,
            ];
        }

        return [
            'success' => $httpCode === 200,
            'response' => $response,
            'http_code' => $httpCode,
        ];
    }

    public function formatPhoneNumber(string $phoneNumber): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = '255'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '255')) {
            $phone = '255'.$phone;
        }

        return $phone;
    }
}
