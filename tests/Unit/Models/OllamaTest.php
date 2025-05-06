<?php

namespace Tests\Unit\Models;

use App\Models\Ollama;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class OllamaTest extends TestCase
{
    protected $ollama;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the Ollama model
        $this->ollama = new Ollama();

        // Mock the Log facade
        Log::shouldReceive('error')->withAnyArgs()->andReturn(null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Http::clearResolvedInstances();
        parent::tearDown();
    }

    public function testGenerateResponseWithChatApi()
    {
        // Arrange
        $prompt = "What is machine learning?";
        $model = "phi4-mini";
        $expectedContent = "Machine learning is a branch of artificial intelligence focused on building systems that learn from data.";

        // Create a mock response for the chat API
        $mockResponse = [
            'message' => [
                'content' => $expectedContent
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response($mockResponse, 200)
        ]);

        // Act
        $response = $this->ollama->generateResponse($prompt, $model, true);

        // Assert
        $this->assertEquals($expectedContent, $response);

        // Verify the request
        Http::assertSent(function ($request) use ($prompt, $model) {
            return $request->url() == 'http://localhost:11434/api/chat' &&
                $request['model'] === $model &&
                $request['messages'][0]['role'] === 'user' &&
                $request['messages'][0]['content'] === $prompt &&
                $request['stream'] === false;
        });
    }

    public function testGenerateResponseWithGenerateApi()
    {
        // Arrange
        $prompt = "Explain quantum computing";
        $model = "deepseek-r1:1.5b";
        $expectedContent = "Quantum computing uses quantum-mechanical phenomena to perform computation.";

        // Create a mock response for the generate API
        $mockResponse = [
            'response' => $expectedContent
        ];

        // Mock HTTP facade
        Http::fake([
            'http://localhost:11434/api/generate' => Http::response($mockResponse, 200)
        ]);

        // Act
        $response = $this->ollama->generateResponse($prompt, $model, false);

        // Assert
        $this->assertEquals($expectedContent, $response);

        // Verify the request
        Http::assertSent(function ($request) use ($prompt, $model) {
            return $request->url() == 'http://localhost:11434/api/generate' &&
                $request['model'] === $model &&
                $request['prompt'] === $prompt &&
                $request['stream'] === false;
        });
    }

    public function testGenerateResponseWithDefaultModel()
    {
        // Arrange
        $prompt = "Default model test";
        $expectedContent = "This is a response from the default model";

        // Create a mock response
        $mockResponse = [
            'message' => [
                'content' => $expectedContent
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response($mockResponse, 200)
        ]);

        // Act
        $response = $this->ollama->generateResponse($prompt);

        // Assert
        $this->assertEquals($expectedContent, $response);

        // Verify default model was used
        Http::assertSent(function ($request) {
            return $request['model'] === 'phi4-mini';
        });
    }

    public function testGenerateResponseApiError()
    {
        // Arrange
        $prompt = "Error test";

        // Mock HTTP facade with error response
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response(['error' => 'Model not found'], 404)
        ]);

        // Act
        $response = $this->ollama->generateResponse($prompt);

        // Assert
        $this->assertEquals("Desculpe, estou tendo problemas para processar sua solicitação.", $response);
    }

    public function testGenerateResponseException()
    {
        // Arrange
        $prompt = "Exception test";

        // Mock HTTP facade to throw exception
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        // Act
        $response = $this->ollama->generateResponse($prompt);

        // Assert
        $this->assertEquals("Desculpe, ocorreu um erro ao processar sua mensagem.", $response);
    }

    public function testEmptyResponseFromChatApi()
    {
        // Arrange
        $prompt = "Empty chat response test";

        // Mock HTTP facade with empty response
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response([], 200)
        ]);

        // Act
        $response = $this->ollama->generateResponse($prompt);

        // Assert
        $this->assertEquals("Não consegui entender.", $response);
    }

    public function testEmptyResponseFromGenerateApi()
    {
        // Arrange
        $prompt = "Empty generate response test";

        // Mock HTTP facade with empty response
        Http::fake([
            'http://localhost:11434/api/generate' => Http::response([], 200)
        ]);

        // Act
        $response = $this->ollama->generateResponse($prompt, null, false);

        // Assert
        $this->assertEquals("Não consegui entender.", $response);
    }

    public function testSetDefaultModel()
    {
        // Arrange
        $newModel = "llama2:7b";
        $prompt = "Test default model setting";

        // Create a mock response
        $mockResponse = [
            'message' => [
                'content' => "Response content"
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response($mockResponse, 200)
        ]);

        // Act
        $this->ollama->setDefaultModel($newModel);
        $this->ollama->generateResponse($prompt);

        // Assert - Verify the model was changed in the request
        Http::assertSent(function ($request) use ($newModel) {
            return $request['model'] === $newModel;
        });
    }

    public function testSetApiUrl()
    {
        // Arrange
        $newApiUrl = "http://ollama-server:11434/api";
        $prompt = "Test API URL setting";

        // Create a mock response
        $mockResponse = [
            'message' => [
                'content' => "Response content"
            ]
        ];

        // Mock HTTP facade
        Http::fake([
            "$newApiUrl/chat" => Http::response($mockResponse, 200)
        ]);

        // Act
        $this->ollama->setApiUrl($newApiUrl);
        $this->ollama->generateResponse($prompt);

        // Assert - Verify the API URL was changed
        Http::assertSent(function ($request) use ($newApiUrl) {
            return strpos($request->url(), $newApiUrl) === 0;
        });
    }
}
