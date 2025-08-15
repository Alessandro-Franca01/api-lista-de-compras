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
        // Melhor validação
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\d{10,15})(,\s*\d{10,15})*$/' // Valida formato de números
            ],
            'message' => 'required|string|min:1|max:4096', // WhatsApp tem limite de caracteres
            'isUsedAI' => 'nullable|boolean'
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
            if ($request->boolean('isUsedAI')) {
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
}
