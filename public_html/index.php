<?php

use Keel\App\Support\SessionRoutes;
use Keel\Core\Env;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;
use Keel\Core\Router;
use Keel\Core\Session;
use Keel\Core\View;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

Env::load($basePath);

error_reporting(Env::get('APP_ENV') === 'production' ? 0 : E_ALL);
ini_set('display_errors', Env::get('APP_DEBUG', false) ? '1' : '0');

// Under Apache, error_log() with no target writes to the server's error log,
// which prefixes every line with the client's IP address. App errors go to
// their own file instead, which records the message and the time and nothing
// about who was visiting.
ini_set('log_errors', '1');
ini_set('error_log', $basePath . '/storage/logs/app.log');

// Public pages never start a session, so they never set a cookie. Only the
// admin panel and its sign-in flow do. See SessionRoutes for why.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (SessionRoutes::requiresSession($requestPath)) {
    Session::start();
}

View::setPath($basePath . '/views');

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// no-referrer: the quick-exit destination and every outbound link (RAINN,
// news sources) must not learn the visitor came from this site.
header('Referrer-Policy: no-referrer');
// Same-origin everything. This is the enforcement behind "zero third-party
// scripts": a script, stylesheet, font, image or fetch from any other origin is
// blocked by the browser even if a view were edited to include one. Inline
// script/style stay allowed because Deck::head() emits an inline script and
// views use inline custom properties (style="--min: 20rem").
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Permissions-Policy: browsing-topics=(), interest-cohort=(), camera=(), microphone=(), geolocation=()');

$router = new Router();
require $basePath . '/routes/web.php';

$request = new Request();

try {
	$router->dispatch($request);
} catch (\Throwable $e) {
	ErrorHandler::render(500, $e);
}
