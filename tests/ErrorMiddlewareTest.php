<?php

declare(strict_types=1);

namespace PerSeo\ErrorRenderer\Test;

use PerSeo\ErrorRenderer\JsonError;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use PerSeo\ErrorRenderer\Test\Mock\ContainerMock;
use Exception;

class ErrorMiddlewareTest extends TestCase
{
    private App $app;
    private ContainerInterface $container;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->container = new ContainerMock();
        $this->app = AppFactory::createFromContainer($this->container);
        $this->app->setBasePath('/api');
        
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->container->set(App::class, $this->app);
        $this->container->set('settings_error', [
            'reporting' => [E_ALL, '~E_NOTICE'],
            'display_error_details' => true,
            'log_errors' => true,
            'log_error_details' => true
        ]);
    }

    public function testErrorMiddlewareRegistration()
    {
        $middlewareFactory = function (ContainerInterface $container) {
            $app = $container->get(App::class);
            $settings = $container->has('settings_error') ? 
                $container->get('settings_error') : [
                    'reporting' => [E_ALL, '~E_NOTICE'],
                    'display_error_details' => true,
                    'log_errors' => true,
                    'log_error_details' => true
                ];
            
            $errorMiddleware = new ErrorMiddleware(
                $app->getCallableResolver(),
                $app->getResponseFactory(),
                (bool)$settings['display_error_details'],
                (bool)$settings['log_errors'],
                (bool)$settings['log_error_details']
            );
            
            $errorHandler = $errorMiddleware->getDefaultErrorHandler();
            $errorHandler->registerErrorRenderer('application/json', JsonError::class);
            
            return $errorMiddleware;
        };
        
        $errorMiddleware = $middlewareFactory($this->container);
        $this->assertInstanceOf(ErrorMiddleware::class, $errorMiddleware);
    }

    public function testErrorMiddlewareHandling()
    {
        $this->logger->expects($this->once())
            ->method('error');
        
        $middlewareFactory = function (ContainerInterface $container) {
            $app = $container->get(App::class);
            $settings = $container->get('settings_error');
            
            $errorMiddleware = new ErrorMiddleware(
                $app->getCallableResolver(),
                $app->getResponseFactory(),
                $settings['display_error_details'],
                $settings['log_errors'],
                $settings['log_error_details']
            );
            
            $errorHandler = $errorMiddleware->getDefaultErrorHandler();
            $errorHandler->registerErrorRenderer('application/json', new JsonError($app, $this->logger));
            
            return $errorMiddleware;
        };
        
        $errorMiddleware = $middlewareFactory($this->container);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test')
            ->withHeader('Accept', 'application/json');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Test error', 400));
        
        $response = $errorMiddleware->process($request, $handler);
        
        // Modifica: Slim ErrorMiddleware restituisce sempre 500
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Test error', $data['message']);
        // Modifica: il codice nell'output JSON sarà quello dell'eccezione (400)
        $this->assertEquals(400, $data['code']);
        $this->assertEquals('/api', $data['basepath']);
    }

    public function testErrorMiddlewareWithoutDetails()
    {
        $this->logger->expects($this->once())
            ->method('error');
        
        $this->container->set('settings_error', [
            'display_error_details' => false,
            'log_errors' => true,
            'log_error_details' => false
        ]);
        
        $middlewareFactory = function (ContainerInterface $container) {
            $app = $container->get(App::class);
            $settings = $container->get('settings_error');
            
            $errorMiddleware = new ErrorMiddleware(
                $app->getCallableResolver(),
                $app->getResponseFactory(),
                $settings['display_error_details'],
                $settings['log_errors'],
                $settings['log_error_details']
            );
            
            $errorHandler = $errorMiddleware->getDefaultErrorHandler();
            $errorHandler->registerErrorRenderer('application/json', new JsonError($app, $this->logger));
            
            return $errorMiddleware;
        };
        
        $errorMiddleware = $middlewareFactory($this->container);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test')
            ->withHeader('Accept', 'application/json');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Test error', 500));
        
        $response = $errorMiddleware->process($request, $handler);
        
        $data = json_decode((string)$response->getBody(), true);
        $this->assertFalse($data['debug']);
        $this->assertArrayNotHasKey('file', $data);
        $this->assertArrayNotHasKey('line', $data);
        $this->assertArrayNotHasKey('trace', $data);
    }
}