<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    public const DEEPSSEK = "deepseek-r1:1.5b";

    public const PHI4_MINI = "phi4-mini";

    public function receive(Request $request)
    {
//        dd('testando end point'); 5583998530445
        $entry = $request->input('entry')[0] ?? [];
        $changes = $entry['changes'][0]['value']['messages'][0] ?? null;

        if (!$changes) {
            return response()->json(['status' => 'no message']);
        }

        $from = $changes['from']; // Número de quem enviou
        $body = $changes['text']['body'] ?? '';

        // Chamada o Deepseek ( FUNCIONANDO )
//        $response = Http::withHeaders([
//            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
//            'Content-Type' => 'application/json',
//        ])->post('https://api.deepseek.com/chat/completions', [
//            'model' => 'deepseek-chat',
//            'messages' => [
//                ['role' => 'user', 'content' => $body],
//            ],
//            'max_tokens' => 100,
//            "stream" => false,
//        ]);

//        dd($response->json(), $response->status(), $response);

        // Como usar a API Chat no lugar da Generate: FUNCIONANDO
        $response = Http::post('http://localhost:11434/api/generate', [
            'model' => self::PHI4_MINI,
            'prompt' => $body,
            'stream' => false
        ]);

        // Como usar a API Chat: Testar
//        $response = Http::post('http://localhost:11434/api/chat', [
//            'model' => self::PHI4_MINI,
//            'messages' => [
//                ['role' => 'user', 'content' => $body],
//            ],
//            "stream" => false,
//        ]);

//        dd($response->json()['response']);
//        dd($response['choices'][0]['message']);

        // Usando API Openai / Deepseek:
//        $reply = $response['choices'][0]['message']['content'] ?? 'Não consegui entender.';

        // Usando o Ollama local:
//        $reply = $response['message']['content'] ?? 'Não consegui entender.';
        $reply = $response->json()['response'];

        // Envia resposta pela API do WhatsApp Cloud: USANDO O GENERATE PARA TESTES INICIAIS!!
//        Http::withToken(env('WHATSAPP_CLOUD_TOKEN'))->post('https://graph.facebook.com/v18.0/' . env('WHATSAPP_PHONE_ID') . '/messages', [
//            'messaging_product' => 'whatsapp',
//            'to' => $from,
//            'text' => [
//                'body' => $response->json()['response']
//            ]
//        ]);

        // Envia resposta pela API do WhatsApp Cloud 5583998530445
//        dd($from, $reply);
        $responseWhatsapp = Http::withToken(env('WHATSAPP_CLOUD_TOKEN_TEST'))->post('https://graph.facebook.com/v18.0/' . env('WHATSAPP_PHONE_ID_TEST') . '/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'text' => [
                'body' => $reply
            ]
        ]);
        dd($responseWhatsapp->json(), $responseWhatsapp->status(), $reply);

        return response()->json(['status' => 'message sent']);
    }
}
