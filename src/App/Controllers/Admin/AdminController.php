<?php

namespace Keel\App\Controllers\Admin;

use Keel\Core\Auth;
use Keel\Core\Controller;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;

/**
 * Shared input handling for the admin panel. Every admin route sits behind
 * AuthMiddleware, RequireAdminMiddleware and CsrfMiddleware (routes/web.php).
 *
 * Forms re-render with errors and the submitted values on failure (status 422)
 * rather than redirecting, so no flash storage is needed. Success redirects
 * with a fixed ?status= key the view maps to a fixed message; nothing the user
 * typed is ever reflected through the query string.
 */
abstract class AdminController extends Controller
{
    protected function adminId(): ?int
    {
        return Auth::id();
    }

    protected function text(Request $request, string $key, int $maxLength): string
    {
        $value = str_replace("\r\n", "\n", trim((string) $request->input($key, '')));

        return mb_substr($value, 0, $maxLength);
    }

    protected function intOrNull(Request $request, string $key): ?int
    {
        $value = trim((string) $request->input($key, ''));

        return ctype_digit($value) ? (int) $value : null;
    }

    protected function checked(Request $request, string $key): bool
    {
        return in_array((string) $request->input($key, ''), ['1', 'on', 'true'], true);
    }

    protected function invalid(string $template, array $data): void
    {
        http_response_code(422);
        $this->view($template, $data);
    }

    protected function notFound(): never
    {
        ErrorHandler::render(404);
    }

    protected static function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    protected static function validHttpUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $value) === 1;
    }
}
