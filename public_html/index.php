<?php

use Keel\App\Support\SameSiteHop;
use Keel\App\Support\SessionRoutes;
use Keel\Core\Env;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;
use Keel\Core\Router;
use Keel\Core\SecurityHeaders;
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

View::setPath($basePath . '/views');

// Nothing is sent until the response is complete, so a fatal error part-way
// through a page can still be replaced by the branded 500 page.
ob_start();
ErrorHandler::registerFatalHandler();

// CSP, Referrer-Policy and the rest, plus HSTS over HTTPS. See SecurityHeaders.
SecurityHeaders::send();

// Public pages never start a session, so they never set a cookie. Only the
// admin panel and its sign-in flow do, and, while submissions are open, the
// survivor pages (each with its own cookie path). See SessionRoutes for why.
$requestPath = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$sessionOptions = SessionRoutes::sessionOptions($requestPath, $basePath);
if ($sessionOptions !== null) {
    // The admin cookie is SameSite=Strict. A link from another site (a
    // sign-in email in webmail) arrives without it; reload from this site
    // first. See SameSiteHop. Not for the survivor pages: nothing there
    // needs a cookie on arrival, and a share link's token is in the URL
    // fragment, which the hop's reload would drop.
    if (SessionRoutes::area($requestPath) === 'admin' && SameSiteHop::needed($_SERVER, $_COOKIE, session_name())) {
        header('Cache-Control: no-store');
        echo SameSiteHop::page(SameSiteHop::target($requestPath, (string) ($_SERVER['QUERY_STRING'] ?? '')));
        exit;
    }

    Session::start($sessionOptions);
}

$router = new Router();
require $basePath . '/routes/web.php';

$request = new Request();

try {
	$router->dispatch($request);
} catch (\Throwable $e) {
	ErrorHandler::render(500, $e);
}
