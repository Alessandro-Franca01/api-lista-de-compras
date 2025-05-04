<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Ollama
{
    protected $apiUrl = 'http://localhost:11434/api';
    protected $defaultModel = 'phi4-mini';

    // Available models
    public const PHI4_MINI = 'phi4-mini';
    public const DEEPSEEK_R1 = 'deepseek-r1:1.5b';

    /**
     * Generate a response using the Ollama API
     *
     * @param string $prompt The user's message
     * @param string|null $modelName The specific Ollama model to use
     * @param bool $useChat Whether to use the chat API (true) or generate API (false)
     * @return string The AI's response
     */
    public function generateResponse(string $prompt, ?string $modelName = null, bool $useChat = true): string
    {
        $modelName = $modelName ?? $this->defaultModel;
        $endpoint = $useChat ? '/chat' : '/generate';

        try {
            $payload = $this->buildPayload($prompt, $modelName, $useChat);
            $response = Http::post($this->apiUrl . $endpoint, $payload);

            if ($response->successful()) {
                return $this->extractResponse($response, $useChat);
            } else {
                Log::error("Ollama API error: " . $response->body());
                return "Desculpe, estou tendo problemas para processar sua solicitação.";
            }
        } catch (\Exception $e) {
            Log::error("Ollama error: " . $e->getMessage());
            return "Desculpe, ocorreu um erro ao processar sua mensagem.";
        }
    }

    /**
     * Build the request payload based on API type
     *
     * @param string $prompt
     * @param string $modelName
     * @param bool $useChat
     * @return array
     */
    protected function buildPayload(string $prompt, string $modelName, bool $useChat): array
    {
        if ($useChat) {
            return [
                'model' => $modelName,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'stream' => false,
            ];
        } else {
            return [
                'model' => $modelName,
                'prompt' => $prompt,
                'stream' => false,
            ];
        }
    }

    /**
     * Extract the response content based on API type
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param bool $useChat
     * @return string
     */
    protected function extractResponse($response, bool $useChat): string
    {
        if ($useChat) {
            return $response['message']['content'] ?? 'Não consegui entender.';
        } else {
            return $response->json()['response'] ?? 'Não consegui entender.';
        }
    }

    /**
     * Set the default model
     *
     * @param string $modelName
     * @return $this
     */
    public function setDefaultModel(string $modelName): self
    {
        $this->defaultModel = $modelName;
        return $this;
    }

    /**
     * Set API base URL (useful for testing or custom deployments)
     *
     * @param string $url
     * @return $this
     */
    public function setApiUrl(string $url): self
    {
        $this->apiUrl = $url;
        return $this;
    }
}
