<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AIService
{
    public function invoke(string $path, array $payload): array
    {
        $response = $this->send($path, fn ($request) => $request->post($this->url($path), $payload));

        return $response->json() ?? throw new AIServiceException('Le service IA a renvoyé une réponse invalide.');
    }

    public function extract(string $path, string $filename, string $mimeType): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new AIServiceException('Le fichier importé n’a pas pu être lu.', 500);
        }
        $response = $this->send(
            '/api/extract',
            fn ($request) => $request->attach('file', $content, $filename, ['Content-Type' => $mimeType])
                ->post($this->url('/api/extract'))
        );

        return $response->json() ?? throw new AIServiceException('Le service de traitement de document a renvoyé une réponse invalide.');
    }

    public function export(array $payload): Response
    {
        return $this->send(
            '/api/export',
            fn ($request) => $request->post($this->url('/api/export'), $payload)
        );
    }

    private function send(string $path, callable $send): Response
    {
        $baseUrl = rtrim((string) config('services.ai_service.url'), '/');
        $key = (string) config('services.ai_service.key');
        if ($baseUrl === '' || $key === '') {
            throw new AIServiceException('Le service IA n’est pas configuré.');
        }

        try {
            $response = $send(
                Http::withHeaders(['X-AI-Service-Key' => $key])
                    ->acceptJson()
                    ->timeout((int) config('services.ai_service.timeout', 60))
            );
        } catch (ConnectionException $exception) {
            throw new AIServiceException('Le service IA est indisponible.', 503, $exception);
        }

        if (! $response->successful()) {
            $message = $response->json('message');
            throw new AIServiceException(
                is_string($message) && $message !== '' ? $message : 'Le service IA a échoué (HTTP '.$response->status().').',
                $response->status()
            );
        }

        return $response;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.ai_service.url'), '/').$path;
    }
}
