<?php

namespace Keel\Core;

class Request
{
    public string $method;
    public string $uri;
    public array $query;
    public array $body;
    public array $headers;
    private string $rawBody;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->query = $_GET;
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];
        $rawBody = $_SERVER['KEEL_RAW_BODY'] ?? file_get_contents('php://input');
        $this->rawBody = is_string($rawBody) ? $rawBody : '';
        $this->body = $this->parseBody();
    }

    private function parseBody(): array
    {
        $contentType = $this->headers['Content-Type'] ?? $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            return json_decode($this->rawBody, true) ?? [];
        }

        return $_POST;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function isJson(): bool
    {
        $contentType = $this->headers['Content-Type'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = $this->headers['Accept'] ?? $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json') || $this->isJson();
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * Whether the visitor reached the site over HTTPS. Decides the HSTS header
     * and the session cookie's Secure flag.
     *
     * Behind a proxy or CDN that ends HTTPS and forwards plain HTTP (a load
     * balancer, Cloudflare's Flexible mode), PHP sees HTTP; set TRUST_PROXY=true
     * there and the proxy's X-Forwarded-Proto decides. Never set it when
     * visitors can reach PHP directly, or anyone could claim HTTPS.
     */
    public static function isHttps(): bool
    {
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        if (strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
            return true;
        }

        if (Env::get('TRUST_PROXY', false) === true) {
            // "https, http" through two proxies: the first is what the visitor used.
            $forwarded = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

            return strtolower(trim($forwarded[0])) === 'https';
        }

        return false;
    }
}
