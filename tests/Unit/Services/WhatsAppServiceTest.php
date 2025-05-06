<?php

namespace Tests\Unit\Services;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class WhatsAppServiceTest extends TestCase
{
    protected $whatsAppService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the WhatsApp service
        $this->whatsAppService = new WhatsAppService();

        // Mock the Log facade
        Log::shouldReceive('error')->withAnyArgs()->andReturn(null);

        // Set up environment variables for testing
        putenv('WHATSAPP_CLOUD_TOKEN=test_token');
        putenv('WHATSAPP_PHONE_ID=123456789');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Http::clearResolvedInstances();
        parent::tearDown();
    }

    public function testSendMessageSuccess()
    {
        // Arrange
        $to = "5583998530445";
        $message = "Hello, this is a test message";

        // Create a mock response
        $mockResponse = [
            'messaging_product' => 'whatsapp',
            'contacts' => [
                ['wa_id' => $to]
            ],
            'messages' => [
                ['id' => 'wamid.123456789']
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'https://graph.facebook.com/v18.0/123456789/messages' => Http::response($mockResponse, 200)
        ]);

        // Act
        $response = $this->whatsAppService->sendMessage($to, $message);

        // Assert
        $this->assertEquals($mockResponse, $response);

        // Verify the request
        Http::assertSent(function ($request) use ($to, $message) {
            return $request->url() == 'https://graph.facebook.com/v18.0/123456789/messages' &&
                $request->hasHeader('Authorization', 'Bearer test_token') &&
                $request['messaging_product'] === 'whatsapp' &&
                $request['to'] === $to &&
                $request['text']['body'] === $message;
        });
    }

    public function testSendMessageApiError()
    {
        // Arrange
        $to = "5583998530445";
        $message = "Error test";

        // Create a mock error response
        $mockResponse = [
            'error' => [
                'message' => 'Invalid WhatsApp business account ID',
                'code' => 100
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'https://graph.facebook.com/v18.0/123456789/messages' => Http::response($mockResponse, 400)
        ]);

        // Act
        $response = $this->whatsAppService->sendMessage($to, $message);

        // Assert
        $this->assertEquals($mockResponse, $response);
    }

    public function testSendMessageException()
    {
        // Arrange
        $to = "5583998530445";
        $message = "Exception test";
        $errorMessage = 'Connection error';

        // Mock HTTP facade to throw exception
        Http::fake(function () use ($errorMessage) {
            throw new \Exception($errorMessage);
        });

        // Act
        $response = $this->whatsAppService->sendMessage($to, $message);

        // Assert
        $this->assertEquals(['error' => $errorMessage], $response);
    }

    public function testParseWebhookValidData()
    {
        // Arrange
        $webhookData = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '5583998530445',
                                        'text' => [
                                            'body' => 'Hello, how are you?'
                                        ],
                                        'timestamp' => '1620000000',
                                        'id' => 'wamid.123456789'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Expected parsed data
        $expected = [
            'from' => '5583998530445',
            'message' => 'Hello, how are you?',
            'timestamp' => '1620000000',
            'id' => 'wamid.123456789'
        ];

        // Act
        $result = $this->whatsAppService->parseWebhook($webhookData);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function testParseWebhookInvalidData()
    {
        // Arrange - Missing required structure
        $webhookData = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [] // No messages key
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Act
        $result = $this->whatsAppService->parseWebhook($webhookData);

        // Assert
        $this->assertNull($result);
    }

    public function testParseWebhookEmptyData()
    {
        // Act
        $result = $this->whatsAppService->parseWebhook([]);

        // Assert
        $this->assertNull($result);
    }

    public function testParseWebhookException()
    {
        // Arrange - Data that will cause an exception when accessed
        $webhookData = null;

        // Act
        $result = $this->whatsAppService->parseWebhook($webhookData);

        // Assert
        $this->assertNull($result);
    }

    public function testSetApiVersion()
    {
        // Arrange
        $newVersion = "v19.0";
        $to = "5583998530445";
        $message = "Test API version";

        // Create a mock response
        $mockResponse = [
            'messaging_product' => 'whatsapp',
            'contacts' => [
                ['wa_id' => $to]
            ],
            'messages' => [
                ['id' => 'wamid.123456789']
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            "https://graph.facebook.com/$newVersion/123456789/messages" => Http::response($mockResponse, 200)
        ]);

        // Act
        $this->whatsAppService->setApiVersion($newVersion);
        $this->whatsAppService->sendMessage($to, $message);

        // Assert - Verify the API version was changed
        Http::assertSent(function ($request) use ($newVersion) {
            return strpos($request->url(), "/$newVersion/") !== false;
        });
    }
}
