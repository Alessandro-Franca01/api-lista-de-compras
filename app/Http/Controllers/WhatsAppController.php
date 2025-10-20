<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppController extends Controller
{
    protected $aiService;
    protected $whatsAppService;

    public function __construct(AIService $aiService, WhatsAppService $whatsAppService)
    {
        $this->aiService = $aiService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Display the WhatsApp message form
     *
     * @return \Illuminate\View\View
     */
    public function showForm()
    {
        return view('whatsapp.form');
    }

    /**
     * Send a message from the web form
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendFromForm(Request $request)
    {
        // TODO: FUNCIONANDO CORRETAMENTE O ENVIO DE MENSAGENS E O USO DO AI
        // Melhor validação - 558398530445
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\d{10,15})(,\s*\d{10,15})*$/' // Valida formato de números
            ],
            'message' => 'required|string|min:1|max:4096', // WhatsApp tem limite de caracteres
            'isUsedAI' => 'nullable|string'
        ], [
            'phone_number.required' => 'O número de telefone é obrigatório.',
            'phone_number.regex' => 'Formato de números inválido. Use apenas números separados por vírgula.',
            'message.required' => 'A mensagem é obrigatória.',
            'message.max' => 'A mensagem não pode ter mais que 4096 caracteres.'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Verificar se WhatsApp está habilitado
            if (!config('services.whatsapp.enabled', false)) {
                return redirect()->back()->with('error', 'Serviço WhatsApp está desabilitado no momento.');
            }

            $phoneNumbers = $this->parsePhoneNumbers($request->input('phone_number'));
            $messageText = $request->input('message');

            // Processar com IA se solicitado
            if ($request->input('isUsedAI') == 'on') {
                $messageText = $this->aiService->getResponse($messageText);
            }

            $results = $this->sendToMultipleNumbers($phoneNumbers, $messageText);

            return $this->handleSendResults($results);

        } catch (\Exception $e) {
            Log::error('Exception sending WhatsApp message: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token'])
            ]);

            return redirect()->back()
                ->with('error', 'Erro inesperado ao enviar mensagem. Tente novamente.')
                ->withInput();
        }
    }

    /**
     * Handle incoming WhatsApp webhook requests
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function receive(Request $request)
    {
        // TODO: Não consigo testar o recebiemnto de mensagens
        //Log::info("Message received", ['request' => $request->all()]);

        try {
            // Verificar se o webhook está habilitado
            if (!config('services.whatsapp.webhook_enabled', true)) {
                return response()->json(['status' => 'webhook disabled'], 503);
            }

            // Parse the webhook data
            $messageData = $this->whatsAppService->parseWebhook($request->all());

            if (!$messageData) {
                return response()->json(['status' => 'no message']);
            }

            // Verificar rate limiting por número
            $from = $messageData['from'];
            if ($this->isRateLimited($from)) {
                Log::warning("Rate limit exceeded for number: {$from}");
                return response()->json(['status' => 'rate limited']);
            }

            $message = $messageData['message'];

            // Log da mensagem recebida (sem dados sensíveis)
            Log::info("Message received", [
                'from' => $this->maskPhoneNumber($from),
                'message_length' => strlen($message),
                'timestamp' => $messageData['timestamp']
            ]);

            // Verificar se a mensagem não está vazia
            if (empty(trim($message))) {
                return response()->json(['status' => 'empty message']);
            }

            // Get AI response
            $reply = $this->aiService->getResponse($message);

            // Send response via WhatsApp
            $responseData = $this->whatsAppService->sendMessage($from, $reply);

            if (isset($responseData['error'])) {
                Log::error("Failed to send response", [
                    'to' => $this->maskPhoneNumber($from),
                    'error' => $responseData['error']
                ]);
            } else {
                Log::info("Response sent successfully", [
                    'to' => $this->maskPhoneNumber($from),
                    'message_id' => $responseData['messages'][0]['id'] ?? 'unknown'
                ]);
            }

            return response()->json(['status' => 'processed']);

        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Webhook verification endpoint
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        $verifyToken = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'provided_token' => $token ? 'provided' : 'missing',
            'expected_token' => $verifyToken ? 'configured' : 'not configured'
        ]);

        return response()->json(['status' => 'verification failed'], 403);
    }

    /**
     * Parse and clean phone numbers
     *
     * @param string $phoneNumbersString
     * @return array
     */
    private function parsePhoneNumbers(string $phoneNumbersString): array
    {
        $numbers = explode(',', $phoneNumbersString);
        $cleanNumbers = [];

        foreach ($numbers as $number) {
            $cleanNumber = preg_replace('/[^0-9]/', '', trim($number));

            if (strlen($cleanNumber) >= 10 && strlen($cleanNumber) <= 15) {
                $cleanNumbers[] = $cleanNumber;
            }
        }

        return array_unique($cleanNumbers); // Remove duplicatas
    }

    /**
     * Send message to multiple numbers
     *
     * @param array $phoneNumbers
     * @param string $message
     * @return array
     */
    private function sendToMultipleNumbers(array $phoneNumbers, string $message): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($phoneNumbers)
        ];

        foreach ($phoneNumbers as $phoneNumber) {
            try {
                $response = $this->whatsAppService->sendMessage($phoneNumber, $message);

                if (isset($response['error'])) {
                    $results['failed'][] = [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'error' => $response['error']
                    ];

                    Log::error('Failed to send message', [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'error' => $response['error']
                    ]);
                } else {
                    $results['success'][] = $this->maskPhoneNumber($phoneNumber);

                    Log::info('Message sent successfully', [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'message_id' => $response['messages'][0]['id'] ?? 'unknown'
                    ]);
                }

                // Rate limiting - pequena pausa entre envios
                usleep(500000); // 0.5 segundos

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'number' => $this->maskPhoneNumber($phoneNumber),
                    'error' => 'Erro interno: ' . $e->getMessage()
                ];

                Log::error('Exception sending to number', [
                    'number' => $this->maskPhoneNumber($phoneNumber),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Handle send results and return appropriate response
     *
     * @param array $results
     * @return \Illuminate\Http\RedirectResponse
     */
    private function handleSendResults(array $results): \Illuminate\Http\RedirectResponse
    {
        $successCount = count($results['success']);
        $failedCount = count($results['failed']);
        $totalCount = $results['total'];

        if ($successCount === $totalCount) {
            return redirect()->back()->with('success',
                "Mensagem enviada com sucesso para {$successCount} número(s)!");
        }

        if ($successCount > 0) {
            $failedNumbers = array_column($results['failed'], 'number');
            return redirect()->back()
                ->with('warning',
                    "Mensagem enviada para {$successCount} de {$totalCount} números. " .
                    "Falhas: " . implode(', ', $failedNumbers))
                ->withInput();
        }

        $errorMessages = array_column($results['failed'], 'error');
        return redirect()->back()
            ->with('error',
                'Falha ao enviar para todos os números. Erros: ' .
                implode('; ', array_unique($errorMessages)))
            ->withInput();
    }

    /**
     * Check if number is rate limited
     *
     * @param string $phoneNumber
     * @return bool
     */
    private function isRateLimited(string $phoneNumber): bool
    {
        $key = 'whatsapp_rate_limit:' . $phoneNumber;
        $maxMessages = config('services.whatsapp.max_messages_per_hour', 10);

        // Usar cache para rate limiting (Redis/Memcached recomendado para produção)
        $currentCount = cache()->get($key, 0);

        if ($currentCount >= $maxMessages) {
            return true;
        }

        cache()->put($key, $currentCount + 1, now()->addHour());

        return false;
    }

    /**
     * Mask phone number for privacy in logs
     *
     * @param string $phoneNumber
     * @return string
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) < 6) {
            return str_repeat('*', strlen($phoneNumber));
        }

        return substr($phoneNumber, 0, 3) . str_repeat('*', strlen($phoneNumber) - 6) . substr($phoneNumber, -3);
    }

    /**
     * Send a template message from the web form
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\d{10,15})(,\s*\d{10,15})*$/' // Valida formato de números
            ],
            'template_name' => 'required|string',
            'template_params' => 'nullable|array',
        ], [
            'phone_number.required' => 'O número de telefone é obrigatório.',
            'phone_number.regex' => 'Formato de números inválido. Use apenas números separados por vírgula.',
            'template_name.required' => 'O nome do template é obrigatório.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            if (!config('services.whatsapp.enabled', false)) {
                return redirect()->back()->with('error', 'Serviço WhatsApp está desabilitado no momento.');
            }

            $phoneNumbers = $this->parsePhoneNumbers($request->input('phone_number'));
            $templateName = $request->input('template_name');
            $templateParams = $request->input('template_params', []);

            $results = ['success' => [], 'failed' => [], 'total' => count($phoneNumbers)];

            foreach ($phoneNumbers as $phoneNumber) {
                try {
                    $response = $this->whatsAppService->sendTemplateMessage(
                        $phoneNumber, $templateName, $templateParams
                    );

                    if (isset($response['error'])) {
                        $results['failed'][] = [
                            'number' => $this->maskPhoneNumber($phoneNumber),
                            'error' => $response['error']
                        ];
                        Log::error('Failed to send template message', [
                            'number' => $this->maskPhoneNumber($phoneNumber),
                            'error' => $response['error']
                        ]);
                    } else {
                        $results['success'][] = $this->maskPhoneNumber($phoneNumber);
                        Log::info('Template message sent successfully', [
                            'number' => $this->maskPhoneNumber($phoneNumber),
                            'message_id' => $response['messages'][0]['id'] ?? 'unknown'
                        ]);
                    }
                    usleep(500000); // 0.5 segundos
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'error' => 'Erro interno: ' . $e->getMessage()
                    ];
                    Log::error('Exception sending template to number', [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->handleSendResults($results);

        } catch (\Exception $e) {
            Log::error('Exception sending WhatsApp template message: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token'])
            ]);

            return redirect()->back()
                ->with('error', 'Erro inesperado ao enviar template. Tente novamente.')
                ->withInput();
        }
    }

    /**
     * Send a media message from the web form
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\d{10,15})(,\s*\d{10,15})*$/' // Valida formato de números
            ],
            'media_file' => 'nullable|file|max:16384', // Max 16MB
            'media_url' => 'nullable|url',
            'caption' => 'nullable|string|max:1024',
        ], [
            'phone_number.required' => 'O número de telefone é obrigatório.',
            'phone_number.regex' => 'Formato de números inválido. Use apenas números separados por vírgula.',
            'media_file.max' => 'O arquivo de mídia não pode ter mais que 16MB.',
            'media_url.url' => 'A URL da mídia é inválida.',
            'caption.max' => 'A legenda não pode ter mais que 1024 caracteres.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            if (!config('services.whatsapp.enabled', false)) {
                return redirect()->back()->with('error', 'Serviço WhatsApp está desabilitado no momento.');
            }

            $phoneNumbers = $this->parsePhoneNumbers($request->input('phone_number'));
            $mediaUrl = $request->input('media_url');
            $caption = $request->input('caption');
            $mediaType = null;

            if ($request->hasFile('media_file')) {
                // Para simplificar, vamos assumir que o arquivo será enviado para um serviço de armazenamento
                // e a URL pública será usada. Aqui, apenas um placeholder.
                // Em um cenário real, você faria upload para S3, Google Cloud Storage, etc.
                // Por enquanto, vamos simular uma URL.
                $uploadedFile = $request->file('media_file');
                $mediaUrl = 'https://example.com/uploads/' . $uploadedFile->hashName(); // Placeholder

                $mimeType = $uploadedFile->getMimeType();
                if (str_starts_with($mimeType, 'image')) {
                    $mediaType = 'image';
                } elseif (str_starts_with($mimeType, 'video')) {
                    $mediaType = 'video';
                } elseif (str_starts_with($mimeType, 'audio')) {
                    $mediaType = 'audio';
                } elseif (in_array($mimeType, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain'])) {
                    $mediaType = 'document';
                } else {
                    return redirect()->back()->with('error', 'Tipo de arquivo de mídia não suportado.');
                }
            } elseif (empty($mediaUrl)) {
                return redirect()->back()->with('error', 'É necessário fornecer um arquivo de mídia ou uma URL.');
            }

            if (!$mediaType && $mediaUrl) {
                // Tentar inferir o tipo de mídia pela URL (simplificado)
                $extension = pathinfo($mediaUrl, PATHINFO_EXTENSION);
                $mediaType = match ($extension) {
                    'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
                    'mp4', '3gp' => 'video',
                    'mp3', 'aac', 'amr', 'ogg' => 'audio',
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt' => 'document',
                    default => null,
                };
                if (!$mediaType) {
                    return redirect()->back()->with('error', 'Não foi possível determinar o tipo de mídia da URL fornecida.');
                }
            }

            $results = ['success' => [], 'failed' => [], 'total' => count($phoneNumbers)];

            foreach ($phoneNumbers as $phoneNumber) {
                try {
                    $response = $this->whatsAppService->sendMediaMessage(
                        $phoneNumber, $mediaUrl, $mediaType, $caption
                    );

                    if (isset($response['error'])) {
                        $results['failed'][] = [
                            'number' => $this->maskPhoneNumber($phoneNumber),
                            'error' => $response['error']
                        ];
                        Log::error('Failed to send media message', [
                            'number' => $this->maskPhoneNumber($phoneNumber),
                            'error' => $response['error']
                        ]);
                    } else {
                        $results['success'][] = $this->maskPhoneNumber($phoneNumber);
                        Log::info('Media message sent successfully', [
                            'number' => $this->maskPhoneNumber($phoneNumber),
                            'message_id' => $response['messages'][0]['id'] ?? 'unknown'
                        ]);
                    }
                    usleep(500000); // 0.5 segundos
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'error' => 'Erro interno: ' . $e->getMessage()
                    ];
                    Log::error('Exception sending media to number', [
                        'number' => $this->maskPhoneNumber($phoneNumber),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->handleSendResults($results);

        } catch (\Exception $e) {
            Log::error('Exception sending WhatsApp media message: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token'])
            ]);

            return redirect()->back()
                ->with('error', 'Erro inesperado ao enviar mídia. Tente novamente.')
                ->withInput();
        }
    }
}
