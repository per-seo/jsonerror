A simple Renderer for Slim4 framework for JSON error Message. Usage is very simple, just add in your container settings this array:
```
'settings_error' => [
	'reporting' => ['E_ALL', '~E_NOTICE'],
        'display_error_details' => true,
	'log_errors' => true,
        'log_error_details' => true
]
```
And enable this Middleware (in the Container DI part of your Slim4 Project) with:
```
<?php

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use PerSeo\ErrorRenderer\JsonError;

ErrorMiddleware::class => function (ContainerInterface $container) {
        $app = $container->get(App::class);
        $settings = ($container->has('settings_error') ? $container->get('settings_error') : [
		'reporting' => ['E_ALL','~E_NOTICE'],
        	'display_error_details' => true,
		'log_errors' => true,
        	'log_error_details' => true
	]);
        $errorMiddleware = new ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            (bool)$settings['display_error_details'],
            (bool)$settings['log_errors'],
            (bool)$settings['log_error_details']
        );
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        $errorHandler->registerErrorRenderer('application/json', JsonError::class);
	$errorHandler->forceContentType('application/json');
        return $errorMiddleware;
    }
```
After this, your Slim 4 500 error page returns a JSON string with all debug informations.

Simple, isn't it?
