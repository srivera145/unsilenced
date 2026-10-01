<?php

namespace Keel\App\Controllers;

use Keel\App\Models\User;
use Keel\App\Services\MagicLinkService;
use Keel\App\Services\OtpService;
use Keel\Core\Activity;
use Keel\Core\Controller;
use Keel\Core\Env;
use Keel\Core\Request;
use Keel\Core\Session;

/**
 * Admin sign-in. Unsilenced has no public accounts: a code or link is only ever
 * sent to an existing user with is_admin = 1 (granted with
 * `php database/console.php admin:grant`). Every other address gets the same
 * response an admin would, so the form does not reveal who has access, and no
 * user row or email is created for it.
 */
class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('auth.login', ['authMethod' => Env::get('AUTH_METHOD', 'both')]);
    }

    public function requestOtp(Request $request): void
    {
        $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->json(['success' => false, 'message' => 'Enter a valid email.'], 422);
        }

        if (!User::isAdmin(User::findByEmail($email))) {
            $this->json(['success' => true, 'message' => 'Code sent.']);
        }

        $result = (new OtpService())->requestCode($email);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    public function verifyOtp(Request $request): void
    {
        $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
        $code = trim((string) $request->input('code'));

        if (!$email || !$code) {
            $this->json(['success' => false, 'message' => 'Email and code are required.'], 422);
        }

        $result = (new OtpService())->verifyCode($email, $code);

        if ($result['success'] && User::isAdmin($result['user'])) {
            $this->loginUser($result['user']);
            // Signing in with a code counts as a fresh code for viewing
            // evidence (FreshOtpMiddleware); a magic link does not.
            Session::put(\Keel\App\Middleware\FreshOtpMiddleware::SESSION_KEY, time());
            $this->json(['success' => true, 'redirect' => $this->postLoginRedirect($result['user'])]);
        }

        $this->json(['success' => false, 'message' => 'Invalid or expired code.'], 422);
    }

    public function requestMagicLink(Request $request): void
    {
        $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->json(['success' => false, 'message' => 'Enter a valid email.'], 422);
        }

        if (!User::isAdmin(User::findByEmail($email))) {
            $this->json(['success' => true, 'message' => 'Link sent.']);
        }

        $result = (new MagicLinkService())->sendLink($email);
        $this->json($result, $result['success'] ? 200 : 422);
    }

    public function verifyMagicLink(Request $request): void
    {
        $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
        $token = trim((string) $request->input('token'));

        if (!$email || !$token) {
            $this->redirect('/login?error=invalid_link');
        }

        $result = (new MagicLinkService())->verifyToken($email, $token);

        if ($result['success'] && User::isAdmin($result['user'])) {
            $this->loginUser($result['user']);
            $this->redirect($this->postLoginRedirect($result['user']));
        }

        $this->redirect('/login?error=invalid_link');
    }

    public function logout(Request $request): void
    {
        Activity::log('user.logout');
        Session::destroy();
        $this->redirect('/login');
    }

    private function loginUser(array $user): void
    {
        Session::regenerate();
        Session::touch();
        Session::put('user_id', $user['id']);
        Session::put('user_email', $user['email']);
        Session::put('theme_preference', $user['theme_preference'] ?? null);
        Activity::log('user.login');
    }

    private function postLoginRedirect(array $user): string
    {
        return '/admin';
    }
}
