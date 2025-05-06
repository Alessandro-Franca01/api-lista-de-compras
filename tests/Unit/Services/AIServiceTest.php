<?php

namespace Tests\Unit\Services;

use App\Models\Deepseek;
use App\Models\Ollama;
use App\Services\AIService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class AIServiceTest extends TestCase
{
    protected $deepseekMock;
    protected $ollamaMock;
    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for the model classes
        $this->deepseekMock = Mockery::mock(Deepseek::class);
        $this->ollamaMock = Mockery::mock(Ollama::class);

        // Create the service with mocked dependencies
        $this->aiService = new AIService($this->deepseekMock, $this->ollamaMock);

        // Mock the Log facade
        Log::shouldReceive('warning')->withAnyArgs()->andReturn(null);
        Log::shouldReceive('error')->withAnyArgs()->andReturn(null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetResponseWithDeepseekModel()
    {
        // Arrange
        $prompt = "Hello, how are you?";
        $expectedResponse = "I'm doing well, thank you for asking!";

        // Set up expectations
        $this->deepseekMock->shouldReceive('generateResponse')
            ->once()
            ->with($prompt)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->aiService->getResponse($prompt, 'deepseek');

        // Assert
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGetResponseWithOllamaModel()
    {
        // Arrange
        $prompt = "What is the capital of France?";
        $modelName = 'phi4-mini';
        $expectedResponse = "The capital of France is Paris.";

        // Set up expectations
        $this->ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->with($prompt, $modelName)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->aiService->getResponse($prompt, 'ollama', $modelName);

        // Assert
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGetResponseWithDefaultModel()
    {
        // Arrange
        $prompt = "Tell me a joke";
        $expectedResponse = "Why did the chicken cross the road?";

        // Use reflection to set the protected property
        $reflectionClass = new \ReflectionClass(AIService::class);
        $property = $reflectionClass->getProperty('defaultModel');
        $property->setAccessible(true);
        $property->setValue($this->aiService, 'deepseek');

        // Set up expectations
        $this->deepseekMock->shouldReceive('generateResponse')
            ->once()
            ->with($prompt)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->aiService->getResponse($prompt);

        // Assert
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGetResponseWithUnknownModelFallsBackToDefault()
    {
        // Arrange
        $prompt = "What's the weather like?";
        $expectedResponse = "I don't have real-time weather data.";

        // Use reflection to set the protected property
        $reflectionClass = new \ReflectionClass(AIService::class);
        $property = $reflectionClass->getProperty('defaultModel');
        $property->setAccessible(true);
        $property->setValue($this->aiService, 'deepseek');

        // Set up expectations for Log and deepseek
        $this->deepseekMock->shouldReceive('generateResponse')
            ->once()
            ->with($prompt)
            ->andReturn($expectedResponse);

        // Act
        $response = $this->aiService->getResponse($prompt, 'unknown_model');

        // Assert
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGetResponseHandlesExceptions()
    {
        // Arrange
        $prompt = "Generate an error";

        // Set up expectations
        $this->deepseekMock->shouldReceive('generateResponse')
            ->once()
            ->with($prompt)
            ->andThrow(new \Exception('API Error'));

        // Act
        $response = $this->aiService->getResponse($prompt, 'deepseek');

        // Assert
        $this->assertEquals("Desculpe, ocorreu um erro ao processar sua mensagem.", $response);
    }
}
