<?php

namespace App\Services;

use App\Models\Deepseek;
use App\Models\Ollama;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $defaultModel;
    protected $deepseek;
    protected $ollama;

    public function __construct(Deepseek $deepseek, Ollama $ollama)
    {
        $this->deepseek = $deepseek;
        $this->ollama = $ollama;
        $this->defaultModel = env('DEFAULT_AI_MODEL', 'deepseek'); // Set default model from env
    }

    /**
     * Get response from AI model
     *
     * @param string $prompt User's message
     * @param string|null $modelType Which AI model to use (deepseek or ollama)
     * @param string|null $modelName Specific model name if using Ollama
     * @return string The AI's response
     */
    public function getResponse(string $prompt, ?string $modelType = null, ?string $modelName = null): string
    {
        $modelType = $modelType ?? $this->defaultModel;

        try {
            if ($modelType === 'deepseek') {
                return $this->deepseek->generateResponse($prompt);
            } else if ($modelType === 'ollama') {
                return $this->ollama->generateResponse($prompt, $modelName);
            } else {
                Log::warning("Unknown model type: {$modelType}, falling back to default");
                return $this->deepseek->generateResponse($prompt);
            }
        } catch (\Exception $e) {
            Log::error("AI response error: " . $e->getMessage());
            return "Desculpe, ocorreu um erro ao processar sua mensagem.";
        }
    }
}
