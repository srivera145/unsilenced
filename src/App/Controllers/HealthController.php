<?php

namespace Keel\App\Controllers;

use Keel\App\Support\Submissions;
use Keel\Core\Controller;
use Keel\Core\Database;
use Keel\Core\Request;

/**
 * GET /up for uptime monitors: 200 when healthy, 503 when not.
 *
 * Unhealthy: the database is unreachable, or (Phase 2.1) SUBMISSIONS_ENABLED
 * is true but the vault cannot work: sodium missing, VAULT_MASTER_KEY
 * missing or invalid, or VAULT_PATH inside public_html. The survivor pages
 * would then quietly show "coming soon", so a monitor has to say so. The
 * response says which part failed, never why: no setting names or paths.
 */
class HealthController extends Controller
{
    public function index(Request $request): void
    {
        try {
            Database::connection()->query('SELECT 1');
            $database = true;
        } catch (\Throwable $exception) {
            $database = false;
        }

        $body = ['status' => 'ok', 'database' => $database];
        $healthy = $database;

        if (Submissions::enabled()) {
            $body['vault'] = Submissions::vaultProblem() === null;
            $healthy = $healthy && $body['vault'];
        }

        $this->json($body, $healthy ? 200 : 503);
    }
}
