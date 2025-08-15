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


// Middleware para Rate Limiting específico do WhatsApp
class WhatsAppRateLimit
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
        // Aplicar apenas em rotas de envio
        if (!$this->isSendRequest($request)) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request);
        $maxAttempts = config('services.whatsapp.max_messages_per_hour', 10);
        $decayMinutes = 60;

        // Verificar se o limite foi excedido
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            Log::warning('WhatsApp rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'key' => $key
            ]);

            return response()->json([
                'error' => 'Muitas tentativas. Tente novamente em alguns minutos.',
                'retry_after' => $this->availableIn($key)
            ], 429);
        }

        // Incrementar contador
        $this->hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Adicionar headers de rate limit
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Determine if this is a send request
     *
     * @param Request $request
     * @return bool
     */
    private function isSendRequest(Request $request): bool
    {
        return $request->isMethod('POST') &&
            (str_contains($request->path(), 'whatsapp/send') ||
                str_contains($request->path(), 'send-whatsapp'));
    }

    /**
     * Resolve request signature for rate limiting
     *
     * @param Request $request
     * @return string
     */
    private function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return 'whatsapp_send:user:' . $user->id;
        }

        return 'whatsapp_send:ip:' . $request->ip();
    }

    /**
     * Determine if the given key has been "accessed" too many times
     *
     * @param string $key
     * @param int $maxAttempts
     * @return bool
     */
    private function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return cache()->get($key, 0) >= $maxAttempts;
    }

    /**
     * Get the number of seconds until the "key" is accessible again
     *
     * @param string $key
     * @return int
     */
    private function availableIn(string $key): int
    {
        $timestamp = cache()->get($key . ':timer', time());
        return max(0, 3600 - (time() - $timestamp));
    }

    /**
     * Increment the counter for a given key for a given decay time
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int
     */
    private function hit(string $key, int $decaySeconds): int
    {
        cache()->put($key . ':timer', time(), $decaySeconds);

        $added = cache()->add($key, 0, $decaySeconds);

        $hits = cache()->increment($key);

        if (!$added && $hits === 1) {
            cache()->put($key, 1, $decaySeconds);
        }

        return $hits;
    }

    /**
     * Calculate the number of remaining attempts
     *
     * @param string $key
     * @param int $maxAttempts
     * @return int
     */
    private function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - cache()->get($key, 0));
    }

    /**
     * Add the limit header information to the given response
     *
     * @param \Illuminate\Http\Response $response
     * @param int $maxAttempts
     * @param int $remainingAttempts
     * @return \Illuminate\Http\Response
     */
    private function addHeaders($response, int $maxAttempts, int $remainingAttempts)
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);

        return $response;
    }
}
