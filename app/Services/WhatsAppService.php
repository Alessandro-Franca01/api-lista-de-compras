<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class WhatsAppService
{
    protected $apiVersion;
    protected $baseUrl = 'https://graph.facebook.com';
    protected $timeout = 30; // segundos
    protected $retryAttempts = 3;

    public function __construct()
    {
        $this->apiVersion = config('services.whatsapp.api_version', 'v23.0');
    }

    /**
     * Send a text message via WhatsApp Cloud API
     *
     * @param string $to Recipient's phone number
     * @param string $message Text to send
     * @return array Response from WhatsApp API
     */
    public function sendMessage(string $to, string $message): array
    {
        return $this->sendWithRetry('sendTextMessage', [$to, $message]);
    }

    /**
     * Send a media message via WhatsApp Cloud API
     *
     * @param string $to Recipient's phone number
     * @param string $mediaUrl URL of the media
     * @param string $mediaType Type of media (image, video, audio, document)
     * @param string|null $caption Optional caption for media
     * @return array Response from WhatsApp API
     */
    public function sendMediaMessage(string $to, string $mediaUrl, string $mediaType = 'image', ?string $caption = null): array
    {
        return $this->sendWithRetry('sendMediaMessageRequest', [$to, $mediaUrl, $mediaType, $caption]);
    }

    /**
     * Send a template message via WhatsApp Cloud API
     *
     * @param string $to Recipient's phone number
     * @param string $templateName Template name
     * @param array $parameters Template parameters
     * @param string $language Language code (default: pt_BR)
     * @return array Response from WhatsApp API
     */
    public function sendTemplateMessage(string $to, string $templateName, array $parameters = [], string $language = 'pt_BR'): array
    {
        return $this->sendWithRetry('sendTemplateMessageRequest', [$to, $templateName, $parameters, $language]);
    }

    /**
     * Mark message as read
     *
     * @param string $messageId
     * @return array Response from WhatsApp API
     */
    public function markAsRead(string $messageId): array
    {
        try {
            $url = $this->buildApiUrl();

            $response = Http::withToken($this->getAccessToken())
                ->timeout($this->timeout)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId
                ]);

            return $this->handleResponse($response);

        } catch (\Exception $e) {
            Log::error("WhatsApp mark as read error: " . $e->getMessage(), [
                'message_id' => $messageId
            ]);
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
            // Verificar estrutura básica do webhook
            if (!isset($data['entry'][0]['changes'][0]['value'])) {
                return null;
            }

            $value = $data['entry'][0]['changes'][0]['value'];

            // Verificar se há mensagens
            if (!isset($value['messages'][0])) {
                // Pode ser uma atualização de status
                if (isset($value['statuses'])) {
                    return $this->parseStatusUpdate($value['statuses'][0]);
                }
                return null;
            }

            $message = $value['messages'][0];
            $contact = $value['contacts'][0] ?? [];

            // Extrair dados básicos
            $messageData = [
                'id' => $message['id'] ?? null,
                'from' => $message['from'] ?? null,
                'timestamp' => $message['timestamp'] ?? time(),
                'type' => $message['type'] ?? 'unknown',
                'contact_name' => $contact['profile']['name'] ?? null,
            ];

            // Processar diferentes tipos de mensagem
            switch ($messageData['type']) {
                case 'text':
                    $messageData['message'] = $message['text']['body'] ?? '';
                    break;

                case 'image':
                case 'video':
                case 'audio':
                case 'document':
                    $messageData['media'] = [
                        'id' => $message[$messageData['type']]['id'] ?? null,
                        'mime_type' => $message[$messageData['type']]['mime_type'] ?? null,
                        'sha256' => $message[$messageData['type']]['sha256'] ?? null,
                        'caption' => $message[$messageData['type']]['caption'] ?? null,
                    ];
                    if ($messageData['type'] === 'document') {
                        $messageData['media']['filename'] = $message[$messageData['type']]['filename'] ?? null;
                    }
                    break;

                case 'location':
                    $messageData['location'] = [
                        'latitude' => $message['location']['latitude'] ?? null,
                        'longitude' => $message['location']['longitude'] ?? null,
                        'name' => $message['location']['name'] ?? null,
                        'address' => $message['location']['address'] ?? null,
                    ];
                    break;

                case 'contacts':
                    $messageData['contacts'] = $message['contacts'] ?? [];
                    break;

                default:
                    $messageData['message'] = 'Tipo de mensagem não suportado: ' . $messageData['type'];
            }

            return $messageData;

        } catch (\Exception $e) {
            Log::error("Webhook parsing error: " . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get media URL from media ID
     *
     * @param string $mediaId
     * @return string|null Media URL or null on failure
     */
    public function getMediaUrl(string $mediaId): ?string
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$mediaId}";

            $response = Http::withToken($this->getAccessToken())
                ->timeout($this->timeout)
                ->get($url);

            if ($response->successful()) {
                return $response->json()['url'] ?? null;
            }

            Log::error("Failed to get media URL", [
                'media_id' => $mediaId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("Media URL retrieval error: " . $e->getMessage(), [
                'media_id' => $mediaId
            ]);
            return null;
        }
    }

    /**
     * Download media content
     *
     * @param string $mediaUrl
     * @return string|null Media content or null on failure
     */
    public function downloadMedia(string $mediaUrl): ?string
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->timeout($this->timeout)
                ->get($mediaUrl);

            if ($response->successful()) {
                return $response->body();
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Media download error: " . $e->getMessage(), [
                'media_url' => $mediaUrl
            ]);
            return null;
        }
    }

    /**
     * Send text message request
     *
     * @param string $to
     * @param string $message
     * @return array
     */
    private function sendTextMessage(string $to, string $message): array
    {
        $url = $this->buildApiUrl();

        $response = Http::withToken($this->getAccessToken())
            ->timeout($this->timeout)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message
                ]
            ]);

        return $this->handleResponse($response);
    }

    /**
     * Send media message request
     *
     * @param string $to
     * @param string $mediaUrl
     * @param string $mediaType
     * @param string|null $caption
     * @return array
     */
    private function sendMediaMessageRequest(string $to, string $mediaUrl, string $mediaType, ?string $caption): array
    {
        $url = $this->buildApiUrl();

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $mediaType,
            $mediaType => [
                'link' => $mediaUrl
            ]
        ];

        if ($caption && in_array($mediaType, ['image', 'video', 'document'])) {
            $payload[$mediaType]['caption'] = $caption;
        }

        $response = Http::withToken($this->getAccessToken())
            ->timeout($this->timeout)
            ->post($url, $payload);

        return $this->handleResponse($response);
    }

    /**
     * Send template message request
     *
     * @param string $to
     * @param string $templateName
     * @param array $parameters
     * @param string $language
     * @return array
     */
    private function sendTemplateMessageRequest(string $to, string $templateName, array $parameters, string $language): array
    {
        $url = $this->buildApiUrl();

        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters)
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($this->getAccessToken())
            ->timeout($this->timeout)
            ->post($url, $payload);

        return $this->handleResponse($response);
    }

    /**
     * Send request with retry mechanism
     *
     * @param string $method
     * @param array $arguments
     * @return array
     */
    private function sendWithRetry(string $method, array $arguments): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $this->$method(...$arguments);
            } catch (RequestException $e) {
                $lastException = $e;

                // Não repetir em caso de erro do cliente (4xx)
                if ($e->response && $e->response->clientError()) {
                    break;
                }

                Log::warning("WhatsApp API attempt {$attempt} failed", [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'status' => $e->response ? $e->response->status() : 'no response'
                ]);

                if ($attempt < $this->retryAttempts) {
                    // Backoff exponencial: 1s, 2s, 4s
                    sleep(pow(2, $attempt - 1));
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error("WhatsApp service error on attempt {$attempt}: " . $e->getMessage());
                break;
            }
        }

        return ['error' => $lastException ? $lastException->getMessage() : 'Unknown error after retries'];
    }

    /**
     * Handle HTTP response
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return array
     */
    private function handleResponse($response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $errorData = $response->json();
        $error = $errorData['error'] ?? [];

        Log::error("WhatsApp API error", [
            'status' => $response->status(),
            'error_code' => $error['code'] ?? 'unknown',
            'error_message' => $error['message'] ?? 'Unknown error',
            'error_details' => $error
        ]);

        return [
            'error' => $error['message'] ?? 'WhatsApp API error',
            'error_code' => $error['code'] ?? null,
            'status_code' => $response->status()
        ];
    }

    /**
     * Parse status update from webhook
     *
     * @param array $status
     * @return array
     */
    private function parseStatusUpdate(array $status): array
    {
        return [
            'type' => 'status_update',
            'id' => $status['id'] ?? null,
            'status' => $status['status'] ?? null,
            'timestamp' => $status['timestamp'] ?? time(),
            'recipient_id' => $status['recipient_id'] ?? null,
        ];
    }

    /**
     * Build the WhatsApp API URL
     *
     * @return string
     */
    private function buildApiUrl(): string
    {
        return "{$this->baseUrl}/{$this->apiVersion}/" . config('services.whatsapp.phone_id') . '/messages';
    }

    /**
     * Get access token
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        return config('services.whatsapp.access_token');
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

    /**
     * Set timeout for requests
     *
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set retry attempts
     *
     * @param int $attempts
     * @return $this
     */
    public function setRetryAttempts(int $attempts): self
    {
        $this->retryAttempts = max(1, $attempts);
        return $this;
    }
}
