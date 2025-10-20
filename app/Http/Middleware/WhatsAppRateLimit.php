<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        return Cache::get($key, 0) >= $maxAttempts;
    }

    /**
     * Get the number of seconds until the "key" is accessible again
     *
     * @param string $key
     * @return int
     */
    private function availableIn(string $key): int
    {
        $timestamp = Cache::get($key . ':timer', time());
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
        Cache::put($key . ':timer', time(), $decaySeconds);

        $added = Cache::add($key, 0, $decaySeconds);

        $hits = Cache::increment($key);

        if (!$added && $hits === 1) {
            Cache::put($key, 1, $decaySeconds);
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
        return max(0, $maxAttempts - Cache::get($key, 0));
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
