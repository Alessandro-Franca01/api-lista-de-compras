<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $request->validate([
            'phone_number' => 'required|string',
            'message' => 'required|string'
        ]);

        try {

            if(!env('WHATSAPP_IS_ALLOWED')){

                return redirect()->back()->with('error', 'Uso do Whatsapp está desabilitado');
            }
            // Format the data to match the expected webhook format
            $phoneNumbers = explode(',', $request->input('phone_number'));
            $messageText = $request->input('message');

            // Add AI service
            if(!empty($request->input('isUsedAI')) && strtolower($request->input('isUsedAI')) == 'on' ){
                $messageText = $this->aiService->getResponse($messageText);
            }

            $errors = [];
            foreach ($phoneNumbers as $phoneNumber) {
                $phoneNumber = trim($phoneNumber);
                if (empty($phoneNumber)) {
                    continue;
                }
                $response = $this->whatsAppService->sendMessage($phoneNumber, $messageText);

                if (isset($response['error'])) {
                    Log::error('Error sending WhatsApp message to ' . $phoneNumber, $response);
                    $errors[] = $phoneNumber . ': ' . ($response['error'] ?? 'Erro desconhecido');
                }
            }

            if (!empty($errors)) {
                return redirect()->back()->with('error', 'Falha ao enviar algumas mensagens: ' . implode(', ', $errors));
            }

            return redirect()->back()->with('success', 'Mensagem enviada com sucesso!');
        } catch (\Exception $e) {
            Log::error('Exception sending WhatsApp message: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro ao enviar mensagem: ' . $e->getMessage());
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
        // Parse the webhook data
        $messageData = $this->whatsAppService->parseWebhook($request->all());

        if (!$messageData) {
            return response()->json(['status' => 'no message']);
        }

        // Extract data
        $from = $messageData['from'];
        $message = $messageData['message'];

        // For debug purposes
        Log::info("Message received from: {$from}", ['message' => $message]);

        // Get AI response (default model defined in AIService)
        // Alternatively, you can specify the model: $this->aiService->getResponse($message, 'ollama', 'phi4-mini')
        $reply = $this->aiService->getResponse($message);

        // Send response via WhatsApp
        $responseData = $this->whatsAppService->sendMessage($from, $reply);

        // Log the result
        Log::info("Message sent to: {$from}", ['response' => $responseData]);

        return response()->json(['status' => 'message sent']);
    }

    /**
     * Optional verification endpoint for WhatsApp webhook setup
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token === env('WHATSAPP_VERIFY_TOKEN')) {
            return response($challenge, 200);
        }

        return response()->json(['status' => 'error'], 403);
    }
}
