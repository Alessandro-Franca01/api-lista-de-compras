<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyWhatsAppWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar se a verificação de assinatura está habilitada
        if (!config('services.whatsapp.webhook_signature_verification', true)) {
            return $next($request);
        }

        // Verificar se é uma requisição de webhook
        if (!$this->isWebhookRequest($request)) {
            return $next($request);
        }

        // Obter a assinatura do header
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            Log::warning('WhatsApp webhook received without signature');
            return response()->json(['error' => 'Missing signature'], 401);
        }

        // Verificar a assinatura
        if (!$this->verifySignature($request->getContent(), $signature)) {
            Log::warning('WhatsApp webhook signature verification failed', [
                'signature' => $signature,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        Log::info('WhatsApp webhook signature verified successfully');

        return $next($request);
    }

    /**
     * Check if this is a webhook request
     *
     * @param Request $request
     * @return bool
     */
    private function isWebhookRequest(Request $request): bool
    {
        return $request->isMethod('POST') &&
            str_contains($request->path(), 'whatsapp/webhook');
    }

    /**
     * Verify the webhook signature
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    private function verifySignature(string $payload, string $signature): bool
    {
        $appSecret = config('services.whatsapp.app_secret');

        if (!$appSecret) {
            Log::error('WhatsApp app secret not configured');
            return false;
        }

        // Remove o prefixo 'sha256=' se presente
        $signature = str_replace('sha256=', '', $signature);

        // Calcular a assinatura esperada
        $expectedSignature = hash_hmac('sha256', $payload, $appSecret);

        // Comparação segura
        return hash_equals($expectedSignature, $signature);
    }
}
