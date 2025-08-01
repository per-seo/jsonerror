<?php

declare(strict_types=1);

namespace PerSeo\ErrorRenderer;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Error\AbstractErrorRenderer;
use Throwable;
use Psr\Log\LoggerInterface;

class JsonError extends AbstractErrorRenderer
{	
    /** @var App<ContainerInterface> */
    private App $app;
	
    private LoggerInterface $logger;

    /**
     * @param App<ContainerInterface> $app
     */
    public function __construct(App $app, LoggerInterface $logger)
    {
        $this->app = $app;
        $this->logger = $logger;
    }

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $this->logger->error($exception);
        
        $data = [
            'debug' => $displayErrorDetails,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'basepath' => $this->app->getBasePath() ?? ''
        ];

        if ($displayErrorDetails) {
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $data['trace'] = $exception->getTraceAsString();
        }
        
        try {
            return (string) json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            $this->logger->error('Error rendering exception: ' . $e->getMessage());
            
            // Return a minimal error response
            $errorData = [
                'message' => 'Error rendering error: ' . $e->getMessage(),
                'code' => 500,
                'basepath' => $this->app->getBasePath() ?? ''
            ];
            
            try {
                return (string) json_encode($errorData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException $e) {
                // If even the error response can't be encoded, return a hardcoded JSON string
                return '{"message":"Error rendering error","code":500,"basepath":"' . 
                       addslashes($this->app->getBasePath() ?? '') . '"}';
            }
        }
    }
}
