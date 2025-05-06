<?php

namespace Tests\Unit\Models;

use App\Models\Deepseek;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;
use Illuminate\Http\Client\Response;

class DeepseekTest extends TestCase
{
    protected $deepseek;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the Deepseek model
        $this->deepseek = new Deepseek();

        // Mock the Log facade
        Log::shouldReceive('error')->withAnyArgs()->andReturn(null);

        // Set up environment variables for testing
        putenv('OPENAI_API_KEY=test_api_key');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Http::clearResolvedInstances();
        parent::tearDown();
    }

    public function testGenerateResponseSuccess()
    {
        // Arrange
        $prompt = "Tell me about artificial intelligence";
        $expectedContent = "Artificial Intelligence refers to systems that can perform tasks requiring human intelligence.";

        // Create a mock response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => $expectedContent
                    ]
                ]
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response($mockResponse, 200)
        ]);

        // Act
        $response = $this->deepseek->generateResponse($prompt);

        // Assert
        $this->assertEquals($expectedContent, $response);

        // Verify the request
        Http::assertSent(function ($request) use ($prompt) {
            return $request->url() == 'https://api.deepseek.com/chat/completions' &&
                $request->hasHeader('Authorization', 'Bearer test_api_key') &&
                $request->hasHeader('Content-Type', 'application/json') &&
                $request['model'] === 'deepseek-chat' &&
                $request['messages'][0]['role'] === 'user' &&
                $request['messages'][0]['content'] === $prompt &&
                $request['max_tokens'] === 100 &&
                $request['stream'] === false;
        });
    }

    public function testGenerateResponseEmptyChoices()
    {
        // Arrange
        $prompt = "Empty response test";

        // Mock HTTP facade with empty choices
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response(['choices' => []], 200)
        ]);

        // Act
        $response = $this->deepseek->generateResponse($prompt);

        // Assert
        $this->assertEquals("Não consegui entender.", $response);
    }

    public function testGenerateResponseApiError()
    {
        // Arrange
        $prompt = "Error test";

        // Mock HTTP facade with error response
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response(['error' => 'Invalid API key'], 401)
        ]);

        // Act
        $response = $this->deepseek->generateResponse($prompt);

        // Assert
        $this->assertEquals("Desculpe, estou tendo problemas para processar sua solicitação.", $response);
    }

    public function testGenerateResponseException()
    {
        // Arrange
        $prompt = "Exception test";

        // Mock HTTP facade to throw exception
        Http::fake(function () {
            throw new \Exception('Connection error');
        });

        // Act
        $response = $this->deepseek->generateResponse($prompt);

        // Assert
        $this->assertEquals("Desculpe, ocorreu um erro ao processar sua mensagem.", $response);
    }

    public function testSetModel()
    {
        // Arrange
        $newModel = 'deepseek-lite';
        $prompt = "Test model setting";

        // Create a mock response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => "Response content"
                    ]
                ]
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response($mockResponse, 200)
        ]);

        // Act
        $this->deepseek->setModel($newModel);
        $this->deepseek->generateResponse($prompt);

        // Assert - Verify the model was changed in the request
        Http::assertSent(function ($request) use ($newModel) {
            return $request['model'] === $newModel;
        });
    }

    public function testSetMaxTokens()
    {
        // Arrange
        $newMaxTokens = 200;
        $prompt = "Test max tokens setting";

        // Create a mock response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => "Response content"
                    ]
                ]
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response($mockResponse, 200)
        ]);

        // Act
        $this->deepseek->setMaxTokens($newMaxTokens);
        $this->deepseek->generateResponse($prompt);

        // Assert - Verify max_tokens was changed in the request
        Http::assertSent(function ($request) use ($newMaxTokens) {
            return $request['max_tokens'] === $newMaxTokens;
        });
    }
}
