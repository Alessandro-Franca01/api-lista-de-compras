<?php

namespace App\Providers;

use App\Models\Deepseek;
use App\Models\Ollama;
use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register Deepseek model
        $this->app->singleton(Deepseek::class, function ($app) {
            $model = new Deepseek();

            // Configure from env if needed
            if (env('DEEPSEEK_MODEL')) {
                $model->setModel(env('DEEPSEEK_MODEL'));
            }

            if (env('DEEPSEEK_MAX_TOKENS')) {
                $model->setMaxTokens((int)env('DEEPSEEK_MAX_TOKENS'));
            }

            return $model;
        });

        // Register Ollama model
        $this->app->singleton(Ollama::class, function ($app) {
            $model = new Ollama();

            // Configure from env if needed
            if (env('OLLAMA_DEFAULT_MODEL')) {
                $model->setDefaultModel(env('OLLAMA_DEFAULT_MODEL'));
            }

            if (env('OLLAMA_API_URL')) {
                $model->setApiUrl(env('OLLAMA_API_URL'));
            }

            return $model;
        });

        // Register AI Service
        $this->app->singleton(AIService::class, function ($app) {
            return new AIService(
                $app->make(Deepseek::class),
                $app->make(Ollama::class)
            );
        });

        // Register WhatsApp Service
        $this->app->singleton(WhatsAppService::class, function ($app) {
            $service = new WhatsAppService();

            if (env('WHATSAPP_API_VERSION')) {
                $service->setApiVersion(env('WHATSAPP_API_VERSION'));
            }

            return $service;
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
