<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\WhatsAppController;
use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class WhatsAppControllerTest extends TestCase
{
    protected $aiServiceMock;
    protected $whatsAppServiceMock;
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for the services
        $this->aiServiceMock = Mockery::mock(AIService::class);
        $this->whatsAppServiceMock = Mockery::mock(WhatsAppService::class);

        // Create the controller with mocked dependencies
        $this->controller = new WhatsAppController(
            $this->aiServiceMock,
            $this->whatsAppServiceMock
        );

        // Mock the Log facade
        Log::shouldReceive('info')->withAnyArgs()->andReturn(null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testReceiveWithValidMessage()
    {
        // Arrange
        $from = "5583998530445";
        $messageText = "Hello, how can you help me?";
        $aiResponse = "I can help you with information and assistance.";

        // Create webhook data
        $webhookData = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => $from,
                                        'text' => [
                                            'body' => $messageText
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Create request mock
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('all')->andReturn($webhookData);

        // Parsed message data
        $parsedData = [
            'from' => $from,
            'message' => $messageText,
            'timestamp' => time(),
            'id' => 'wamid.123456789'
        ];

        // WhatsApp API response
        $whatsAppResponse = [
            'messaging_product' => 'whatsapp',
            'contacts' => [['wa_id' => $from]],
            'messages' => [['id' => 'wamid.response123']]
        ];

        // Set up expectations
        $this->whatsAppServiceMock->shouldReceive('parseWebhook')
            ->once()
            ->with($webhookData)
            ->andReturn($parsedData);

        $this->aiServiceMock->shouldReceive('getResponse')
            ->once()
            ->with($messageText)
            ->andReturn($aiResponse);

        $this->whatsAppServiceMock->shouldReceive('sendMessage')
            ->once()
            ->with($from, $aiResponse)
            ->andReturn($whatsAppResponse);

        // Act
        $response = $this->controller->receive($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"status":"message sent"}', $response->getContent());
    }

    // TODO: Complete this tests after when I have more time
//    public function testReceiveWithNoMessage()
//    {
//        // Arrange
//        // Create webhook data with no message
//        $webhookData = [
//            'entry' => [
//                [
//                    'changes' => [
//                        [
//                            'value' => [
//                                // No messages key
//                            ]
//                        ]
//                    ]
//                ]
//            ]
//        ];
//
//        // Create request mock
//        $request = Mockery::mock(Request::class);
//        $request->shoul
//            }
}
