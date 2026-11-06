<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for GroqCloud (OpenAI-compatible chat completions).
 *
 * @see https://console.groq.com/docs/overview
 * @see https://console.groq.com/docs/openai
 */
class GroqService
{
    public function enabled(): bool
    {
        return filled(config('services.groq.key'));
    }

    public function model(): string
    {
        return (string) config('services.groq.model', 'openai/gpt-oss-20b');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, model: string, usage: array<string, mixed>|null, raw: array<string, mixed>}
     */
    public function chat(array $messages, array $options = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Groq is not configured. Set GROQ_API_KEY in .env.');
        }

        $payload = array_merge([
            'model' => $options['model'] ?? $this->model(),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
        ], collect($options)->except(['model', 'temperature'])->all());

        try {
            $response = Http::baseUrl(rtrim((string) config('services.groq.base_url'), '/'))
                ->withToken((string) config('services.groq.key'))
                ->acceptJson()
                ->timeout((int) config('services.groq.timeout', 60))
                ->post('/chat/completions', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body)
                ? (string) data_get($body, 'error.message', $e->getMessage())
                : $e->getMessage();

            throw new RuntimeException('Groq API error: '.$message, (int) $e->getCode(), $e);
        }

        $content = (string) data_get($response, 'choices.0.message.content', '');

        return [
            'content' => $content,
            'model' => (string) data_get($response, 'model', $payload['model']),
            'usage' => data_get($response, 'usage'),
            'raw' => is_array($response) ? $response : [],
        ];
    }

    /**
     * Convenience helper for a single user prompt with optional system instructions.
     *
     * @param  array<string, mixed>  $options
     */
    public function complete(string $prompt, ?string $system = null, array $options = []): string
    {
        $messages = [];

        if (filled($system)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $this->chat($messages, $options)['content'];
    }

    /**
     * Transcribe audio via Groq Whisper (OpenAI-compatible audio endpoint).
     */
    public function transcribe(string $absolutePath, string $filename = 'audio.webm', ?string $language = null): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Groq is not configured. Set GROQ_API_KEY in .env.');
        }

        if (! is_file($absolutePath)) {
            throw new RuntimeException('Audio file not found.');
        }

        $fields = [
            'model' => (string) config('services.groq.whisper_model', 'whisper-large-v3-turbo'),
            'response_format' => 'json',
        ];

        if (filled($language)) {
            $fields['language'] = $language;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.groq.base_url'), '/'))
                ->withToken((string) config('services.groq.key'))
                ->timeout((int) config('services.groq.timeout', 60))
                ->attach('file', file_get_contents($absolutePath), $filename)
                ->post('/audio/transcriptions', $fields)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body)
                ? (string) data_get($body, 'error.message', $e->getMessage())
                : $e->getMessage();

            throw new RuntimeException('Groq transcription error: '.$message, (int) $e->getCode(), $e);
        }

        return trim((string) data_get($response, 'text', ''));
    }
}
