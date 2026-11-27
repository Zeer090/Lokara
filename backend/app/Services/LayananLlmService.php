<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LayananLlmService
 *
 * Abstraksi terpadu untuk memanggil model AI gratis (Google Gemini, Groq, OpenRouter).
 * Jika API key belum dikonfigurasi atau kuota/jaringan bermasalah, mengembalikan null
 * agar caller dapat jatuh ke fallback lokal yang aman.
 */
class LayananLlmService
{
    /**
     * Cek apakah ada minimal satu provider LLM gratis yang aktif (memiliki API key).
     */
    public function tersedia(): bool
    {
        return $this->tentukanProvider() !== null;
    }

    /**
     * Nama provider aktif saat ini (gemini, groq, openrouter, atau null).
     */
    public function tentukanProvider(): ?string
    {
        $pref = config('services.llm.provider', 'auto');

        $keys = [
            'gemini'     => config('services.llm.gemini_api_key'),
            'groq'       => config('services.llm.groq_api_key'),
            'openrouter' => config('services.llm.openrouter_api_key'),
        ];

        if ($pref !== 'auto' && !empty($keys[$pref])) {
            return $pref;
        }

        foreach (['gemini', 'groq', 'openrouter'] as $p) {
            if (!empty($keys[$p])) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Mengirim percakapan ke LLM gratis dan mengembalikan teks balasan.
     *
     * @param  string $systemPrompt Instruksi sistem (peran, aturan, konteks data)
     * @param  array  $messages     Array of ['role' => 'user'|'assistant', 'content' => '...']
     * @param  array  $options      ['temperature' => float, 'max_tokens' => int, 'json' => bool]
     * @return string|null
     */
    public function chat(string $systemPrompt, array $messages, array $options = []): ?string
    {
        $provider = $this->tentukanProvider();
        if (!$provider) {
            return null;
        }

        $temperature = $options['temperature'] ?? 0.3;
        $maxTokens   = $options['max_tokens'] ?? 450;
        $expectJson  = $options['json'] ?? false;

        try {
            return match ($provider) {
                'gemini'     => $this->panggilGemini($systemPrompt, $messages, $temperature, $maxTokens, $expectJson),
                'groq'       => $this->panggilOpenAiCompatible(
                    'https://api.groq.com/openai/v1/chat/completions',
                    config('services.llm.groq_api_key'),
                    config('services.llm.groq_model', 'llama-3.3-70b-versatile'),
                    $systemPrompt,
                    $messages,
                    $temperature,
                    $maxTokens,
                    $expectJson
                ),
                'openrouter' => $this->panggilOpenAiCompatible(
                    'https://openrouter.ai/api/v1/chat/completions',
                    config('services.llm.openrouter_api_key'),
                    config('services.llm.openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free'),
                    $systemPrompt,
                    $messages,
                    $temperature,
                    $maxTokens,
                    $expectJson
                ),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning("LayananLlmService ({$provider}) gagal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Memanggil LLM dan mengurai respons JSON menjadi array asosiatif.
     */
    public function chatJson(string $systemPrompt, array $messages, array $options = []): ?array
    {
        $options['json'] = true;
        $raw = $this->chat($systemPrompt, $messages, $options);
        if (!$raw) {
            return null;
        }

        // Bersihkan markdown code fence jika ada
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function panggilGemini(string $systemPrompt, array $messages, float $temperature, int $maxTokens, bool $expectJson): ?string
    {
        $apiKey = config('services.llm.gemini_api_key');
        $primaryModel = config('services.llm.gemini_model', 'gemini-flash-latest');

        $contents = [];
        foreach ($messages as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => (string) ($m['content'] ?? '')]],
            ];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if ($expectJson) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $kandidatModel = array_values(array_unique([
            $primaryModel,
            'gemini-flash-latest',
            'gemini-3.7-flash',
            'gemini-flash-lite-latest',
        ]));

        foreach ($kandidatModel as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

            $res = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->timeout(20)->post($url, $payload);

            if ($res->successful()) {
                $text = data_get($res->json(), 'candidates.0.content.parts.0.text');
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }

            // Jika error bukan karena model sibuk/tidak ditemukan (mis. 400/401/403), hentikan
            if (!in_array($res->status(), [404, 429, 500, 503], true)) {
                Log::warning('Gemini API error', ['model' => $model, 'status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            Log::info("Gemini model {$model} mengembalikan HTTP {$res->status()}, mencoba model cadangan...");
        }

        return null;
    }

    protected function panggilOpenAiCompatible(
        string $endpoint,
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $messages,
        float $temperature,
        int $maxTokens,
        bool $expectJson
    ): ?string {
        $formatted = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $m) {
            $formatted[] = [
                'role'    => ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($m['content'] ?? ''),
            ];
        }

        $payload = [
            'model'       => $model,
            'messages'    => $formatted,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ];

        if ($expectJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $res = Http::withToken($apiKey)
            ->timeout(25)
            ->post($endpoint, $payload);

        if (!$res->successful()) {
            Log::warning('OpenAI-compatible LLM error', ['endpoint' => $endpoint, 'status' => $res->status(), 'body' => $res->body()]);
            return null;
        }

        $text = data_get($res->json(), 'choices.0.message.content');
        return is_string($text) ? trim($text) : null;
    }
}
