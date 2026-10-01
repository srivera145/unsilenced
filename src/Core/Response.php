<?php

namespace Keel\Core;

class Response
{
    private static bool $captureMode = false;

    public static function setCaptureMode(bool $enabled): void
    {
        self::$captureMode = $enabled;
    }

    public static function isCapturing(): bool
    {
        return self::$captureMode;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        if (self::$captureMode) {
            throw new CapturedResponseException($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
        }

        self::setStatus($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        if (self::$captureMode) {
            throw new CapturedResponseException($status, ['Location' => $to], '');
        }

        self::setStatus($status);
        header("Location: {$to}");
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        if (self::$captureMode) {
            throw new CapturedResponseException($status, [], $message ?: "Error {$status}");
        }

        self::setStatus($status);
        echo $message ?: "Error {$status}";
        exit;
    }

    public static function raw(string $body, int $status = 200, array $headers = []): never
    {
        if (self::$captureMode) {
            throw new CapturedResponseException($status, $headers, $body);
        }

        self::setStatus($status);

        foreach ($headers as $name => $value) {
            header((string) $name . ': ' . (string) $value);
        }

        echo $body;
        exit;
    }

    /**
     * Sends a body produced piece by piece: $produce is handed a writer and
     * calls it as often as it likes. For decrypted evidence downloads, which
     * should never be held whole in memory or written to disk. The front
     * controller's output buffer is closed first so each piece goes straight
     * out. A failure part-way is logged and the response simply ends: the
     * headers are already sent, so no error page can follow.
     *
     * @param callable(callable(string): void): void $produce
     */
    public static function stream(callable $produce, int $status = 200, array $headers = []): never
    {
        if (self::$captureMode) {
            $body = '';
            $produce(static function (string $chunk) use (&$body): void {
                $body .= $chunk;
            });

            throw new CapturedResponseException($status, $headers, $body);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        self::setStatus($status);
        foreach ($headers as $name => $value) {
            header((string) $name . ': ' . (string) $value);
        }

        try {
            $produce(static function (string $chunk): void {
                echo $chunk;
                flush();
            });
        } catch (\Throwable $exception) {
            error_log(ErrorHandler::logLine($exception));
        }

        exit;
    }

    private static function setStatus(int $status): void
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $texts = [
            200 => 'OK',
            302 => 'Found',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            404 => 'Not Found',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        ];

        if ($status !== 419) {
            http_response_code($status);

            if (http_response_code() === $status) {
                return;
            }
        }

        $text = $texts[$status] ?? 'OK';
        header('Status: ' . $status . ' ' . $text);
        header($protocol . ' ' . $status . ' ' . $text);
    }
}
