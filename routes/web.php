<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

/*
|--------------------------------------------------------------------------
| WhatsApp Routes
|--------------------------------------------------------------------------
|
| Rotas para funcionalidades do WhatsApp Business API
|
*/

// Grupo de rotas para WhatsApp com middleware comum
Route::prefix('whatsapp')->name('whatsapp.')->group(function () {

    // Rota pública para verificação do webhook (GET)
    Route::get('/webhook', [WhatsAppController::class, 'verify'])
        ->name('webhook.verify');

    // Rota pública para receber mensagens do webhook (POST)
    Route::post('/webhook', [WhatsAppController::class, 'receive'])
        ->name('webhook.receive')
        ->middleware(['verify.whatsapp.webhook']);

    // Rotas protegidas por autenticação (se necessário)
    Route::middleware(['web'])->group(function () {

        // Exibir formulário de envio
        Route::get('/send', [WhatsAppController::class, 'showForm'])
            ->name('send.form');

        // Enviar mensagem simples via formulário
        Route::post('/send', [WhatsAppController::class, 'sendFromForm'])
            ->name('send')
            ->middleware(['whatsapp.rate.limit']);

        // Enviar template
        Route::post('/send/template', [WhatsAppController::class, 'sendTemplate'])
            ->name('send.template')
            ->middleware(['whatsapp.rate.limit']);

        // Enviar mídia
        Route::post('/send/media', [WhatsAppController::class, 'sendMedia'])
            ->name('send.media')
            ->middleware(['whatsapp.rate.limit']);
    });
});

// Rotas API para integração externa
Route::prefix('api/whatsapp')->name('api.whatsapp.')->middleware(['api', 'auth:sanctum'])->group(function () {

    // Enviar mensagem via API
    Route::post('/send', [WhatsAppController::class, 'sendMessageAPI'])
        ->name('send.api')
        ->middleware(['throttle:10,1']); // 10 mensagens por minuto

    // Enviar template via API
    Route::post('/send/template', [WhatsAppController::class, 'sendTemplateAPI'])
        ->name('send.template.api')
        ->middleware(['throttle:10,1']);

    // Enviar mídia via API
    Route::post('/send/media', [WhatsAppController::class, 'sendMediaAPI'])
        ->name('send.media.api')
        ->middleware(['throttle:5,1']); // 5 mídias por minuto

    // Obter URL de mídia
    Route::get('/media/{mediaId}', [WhatsAppController::class, 'getMediaUrl'])
        ->name('media.url');

    // Baixar mídia
    Route::get('/media/{mediaId}/download', [WhatsAppController::class, 'downloadMedia'])
        ->name('media.download');

    // Marcar mensagem como lida
    Route::post('/messages/{messageId}/read', [WhatsAppController::class, 'markAsRead'])
        ->name('message.read');

    // Obter templates disponíveis
    Route::get('/templates', [WhatsAppController::class, 'getTemplates'])
        ->name('templates');

    // Obter estatísticas de envio
    Route::get('/stats', [WhatsAppController::class, 'getStats'])
        ->name('stats');
});

// Rotas alternativas (compatibilidade com código atual)
Route::get('/whatsapp', [WhatsAppController::class, 'showForm'])->name('whatsapp.form');
Route::post('/send-whatsapp', [WhatsAppController::class, 'sendFromForm'])
    ->name('send.whatsapp')
    ->middleware(['whatsapp.rate.limit']);

// Registrar middleware no Kernel.php
/*
Adicionar no app/Http/Kernel.php:

protected $routeMiddleware = [
    // ... outros middlewares
    'verify.whatsapp.webhook' => \App\Http\Middleware\VerifyWhatsAppWebhook::class,
    'whatsapp.rate.limit' => \App\Http\Middleware\WhatsAppRateLimit::class,
];
*/
