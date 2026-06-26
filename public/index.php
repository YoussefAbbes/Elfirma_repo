<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

if (is_file(dirname(__DIR__).'/vendor/autoload_runtime.php')) {
    require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

    return static function (array $context) {
        return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    };
}

require_once dirname(__DIR__).'/vendor/autoload.php';

$debug = (bool) ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? false);
if ($debug) {
    umask(0000);
    Debug::enable();
}

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'prod';
$kernel = new Kernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
