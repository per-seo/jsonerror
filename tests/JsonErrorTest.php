<?php

declare(strict_types=1);

namespace Tests\ErrorRenderer;

use PerSeo\ErrorRenderer\JsonError;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Slim\App;
use PerSeo\ErrorRenderer\Test\Mock\ContainerMock;
use Exception;
use JsonException;
use Throwable;

class JsonErrorTest extends TestCase
{
    private App $app;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $container = new ContainerMock();
        $this->app = new App(new \Slim\Psr7\Factory\ResponseFactory(), $container);
        $this->app->setBasePath('/api');
        
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testBasicErrorRendering()
    {
        $this->logger->expects($this->once())
            ->method('error');
        
        $renderer = new JsonError($this->app, $this->logger);
        $exception = new Exception('Test error', 400);
        
        $result = $renderer->__invoke($exception, false);
        
        $data = json_decode($result, true);
        
        $this->assertFalse($data['debug']);
        $this->assertEquals(400, $data['code']);
        $this->assertEquals('Test error', $data['message']);
        $this->assertEquals('/api', $data['basepath']);
        $this->assertArrayNotHasKey('file', $data);
        $this->assertArrayNotHasKey('line', $data);
        $this->assertArrayNotHasKey('trace', $data);
    }

    public function testDetailedErrorRendering()
    {
        $this->logger->expects($this->once())
            ->method('error');
        
        $renderer = new JsonError($this->app, $this->logger);
        $exception = new Exception('Test error', 500);
        
        $result = $renderer->__invoke($exception, true);
        
        $data = json_decode($result, true);
        
        $this->assertTrue($data['debug']);
        $this->assertEquals(500, $data['code']);
        $this->assertEquals('Test error', $data['message']);
        $this->assertEquals('/api', $data['basepath']);
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('line', $data);
        $this->assertArrayHasKey('trace', $data);
    }

    public function testJsonEncodingFailure()
    {
        $this->logger->expects($this->exactly(2))
            ->method('error');
        
        // Creiamo una classe che forza l'errore di JSON encoding
        $renderer = new class($this->app, $this->logger) extends JsonError {
            public function __invoke(Throwable $exception, bool $displayErrorDetails): string
            {
                // Chiamiamo il parent per il logging
                parent::__invoke($exception, $displayErrorDetails);
                
                // Forziamo un errore di JSON encoding
                throw new JsonException('Simulated JSON encoding error');
            }
        };
        
        $exception = new Exception('Test error', 500);
        $result = $renderer->__invoke($exception, true);
        
        // Verifichiamo il fallback
        $data = json_decode($result, true);
        $this->assertEquals('Error rendering error: Simulated JSON encoding error', $data['message']);
        $this->assertEquals(500, $data['code']);
        $this->assertEquals('/api', $data['basepath']);
    }

    public function testDoubleJsonEncodingFailure()
    {
        $this->logger->expects($this->exactly(3))
            ->method('error');
        
        // Creiamo una classe che forza errori di JSON encoding sia nel primo che nel secondo tentativo
        $renderer = new class($this->app, $this->logger) extends JsonError {
            public function __invoke(Throwable $exception, bool $displayErrorDetails): string
            {
                // Chiamiamo il parent per il logging
                parent::__invoke($exception, $displayErrorDetails);
                
                // Primo tentativo fallisce
                throw new JsonException('First JSON encoding error');
            }
            
            protected function jsonEncodeFallback(array $data): string
            {
                // Anche il fallback fallisce
                throw new JsonException('Second JSON encoding error');
            }
        };
        
        $exception = new Exception('Test error');
        $result = $renderer->__invoke($exception, true);
        
        // Verifichiamo l'ultimo fallback hardcoded
        $this->assertEquals(
            '{"message":"Error rendering error","code":500,"basepath":"/api"}',
            $result
        );
    }
    /**
    * Estrae l'ultimo fallback JSON dalla classe JsonError
    */
    private function getHardcodedFallback(): string
    {
        $renderer = new JsonError($this->app, $this->logger);
        $exception = new JsonException('Test');
        
        // Forziamo due errori di encoding per ottenere l'ultimo fallback
        try {
            $data = ['test' => "\x80"]; // Dati non validi
            json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            try {
                $renderer->jsonEncode(['test' => "\x80"]);
            } catch (JsonException $e) {
                return $renderer->renderFallbackError($e);
            }
        }
        
        return '';
    }
}