<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Deepseek
{
    protected $apiUrl = 'https://api.deepseek.com/chat/completions';
    protected $model = 'deepseek-chat';
    protected $maxTokens = 100;

    /**
     * Generate a response using the Deepseek API
     *
     * @param string $prompt The user's message
     * @return string The AI's response
     */
    public function generateResponse(string $prompt): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $this->maxTokens,
                'stream' => false,
            ]);

            if ($response->successful()) {
                return $response['choices'][0]['message']['content'] ?? 'Não consegui entender.';
            } else {
                Log::error("Deepseek API error: " . $response->body());
                return "Desculpe, estou tendo problemas para processar sua solicitação.";
            }
        } catch (\Exception $e) {
            Log::error("Deepseek error: " . $e->getMessage());
            return "Desculpe, ocorreu um erro ao processar sua mensagem.";
        }
    }

    /**
     * Set the model to use
     *
     * @param string $model
     * @return $this
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set max tokens for the response
     *
     * @param int $maxTokens
     * @return $this
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }
}
