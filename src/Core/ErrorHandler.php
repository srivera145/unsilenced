<?php

namespace Keel\Core;

class ErrorHandler
{
    private const FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /**
     * A PHP fatal error (out of memory, a parse error in an included file) is
     * not an exception, so the front controller's catch never sees it, and
     * with display_errors off the visitor would get a blank page with no quick
     * exit and no hotline. This renders the branded 500 page instead. PHP has
     * already written the error to the log. Works because index.php buffers
     * the response, so nothing has been sent yet.
     */
    public static function registerFatalHandler(): void
    {
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], self::FATAL_ERRORS, true)) {
                return;
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if (!headers_sent()) {
                self::render(500);
            }
        });
    }

    public static function render(int $status, ?\Throwable $e = null): never
    {
        if ($e !== null) {
            error_log(self::logLine($e));
        }

        $status = $status === 404 ? 404 : 500;
        http_response_code($status);

        $debug = (bool) Env::get('APP_DEBUG', false);
        $exception = $debug ? $e : null;
        $title = $status === 404 ? 'Page Not Found' : 'Server Error';
        $template = $status === 404 ? 'errors.404' : 'errors.500';

        ob_start();

        try {
            View::render($template, [
                'title' => $title,
                'exception' => $exception,
            ]);
        } catch (\Throwable $viewException) {
            echo $status === 404 ? 'Page not found' : 'Server error';
        }

        $body = (string) ob_get_clean();

        // Under the test harness, hand the page back instead of exiting, so a
        // test can assert that a route 404s.
        if (Response::isCapturing()) {
            throw new CapturedResponseException($status, [], $body);
        }

        echo $body;
        exit;
    }

    /**
     * The error-log line for an uncaught exception: its class, message, file
     * and line. Nothing from the request is added (no URL, query string, IP or
     * header), and the message is scrubbed, because an exception message can
     * quote the input that caused it: a magic-link URL carries a sign-in token
     * and an email address, and a search URL carries what someone searched for.
     */
    public static function logLine(\Throwable $e): string
    {
        return '[Keel] Uncaught ' . $e::class . ': ' . self::scrub($e->getMessage())
            . ' in ' . $e->getFile() . ':' . $e->getLine();
    }

    /**
     * Removes what could identify a visitor from text bound for a log: the
     * current request's query string and client address wherever they appear,
     * any other query string on a URL or path, and every IPv4 or IPv6 address.
     */
    public static function scrub(string $text): string
    {
        foreach (['QUERY_STRING', 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value !== '') {
                $text = str_replace($value, $key === 'QUERY_STRING' ? '[query removed]' : '[ip removed]', $text);
            }
        }

        $text = (string) preg_replace('/(?<=[\w\/.%-])\?[^\s"\'<>]+/u', '?[query removed]', $text);
        $text = (string) preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip removed]', $text);

        // IPv6: the full eight-group form, or any compressed form with "::".
        // The look-arounds keep "Class::method" from matching.
        return (string) preg_replace(
            '/(?<![\w:])(?:(?:[0-9a-f]{1,4}:){7}[0-9a-f]{1,4}|(?:[0-9a-f]{1,4}(?::[0-9a-f]{1,4}){0,6})?::(?:[0-9a-f]{1,4}(?::[0-9a-f]{1,4}){0,6})?)(?![\w:])/i',
            '[ip removed]',
            $text
        );
    }
}
