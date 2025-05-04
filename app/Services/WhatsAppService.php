<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiVersion = 'v18.0';
    protected $baseUrl = 'https://graph.facebook.com';

    /**
     * Send a text message via WhatsApp Cloud API
     *
     * @param string $to Recipient's phone number
     * @param string $message Text to send
     * @return array Response from WhatsApp API
     */
    public function sendMessage(string $to, string $message): array
    {
        try {
            $url = $this->buildApiUrl();

            $response = Http::withToken(env('WHATSAPP_CLOUD_TOKEN'))
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'text' => [
                        'body' => $message
                    ]
                ]);

            if (!$response->successful()) {
                Log::error("WhatsApp API error: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("WhatsApp service error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Parse incoming WhatsApp webhook data
     *
     * @param array $data Webhook data
     * @return array|null Parsed message data or null if invalid
     */
    public function parseWebhook(array $data): ?array
    {
        try {
            $entry = $data['entry'][0] ?? [];
            $changes = $entry['changes'][0]['value']['messages'][0] ?? null;

            if (!$changes) {
                return null;
            }

            return [
                'from' => $changes['from'] ?? null,
                'message' => $changes['text']['body'] ?? '',
                'timestamp' => $changes['timestamp'] ?? time(),
                'id' => $changes['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error("Webhook parsing error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the WhatsApp API URL
     *
     * @return string
     */
    protected function buildApiUrl(): string
    {
        return "{$this->baseUrl}/{$this->apiVersion}/" . env('WHATSAPP_PHONE_ID') . '/messages';
    }

    /**
     * Set the API version
     *
     * @param string $version
     * @return $this
     */
    public function setApiVersion(string $version): self
    {
        $this->apiVersion = $version;
        return $this;
    }
}
