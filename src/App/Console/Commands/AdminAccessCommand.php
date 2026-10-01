<?php

namespace Keel\App\Console\Commands;

use Keel\App\Models\User;

/**
 * admin:grant <email> / admin:revoke <email>
 *
 * The only way to make an admin. There is no sign-up and no UI for it, so an
 * account with panel access always traces back to someone at a shell.
 */
class AdminAccessCommand extends Command
{
    public function __construct(private readonly bool $grant, ?callable $output = null, ?callable $errorOutput = null)
    {
        parent::__construct($output, $errorOutput);
    }

    public static function usage(): string
    {
        return 'admin:grant <email> | admin:revoke <email>';
    }

    public function handle(array $arguments): int
    {
        [$positional] = $this->parse($arguments);
        $email = filter_var(trim((string) ($positional[0] ?? '')), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            return $this->fail('A valid email address is required. Usage: php database/console.php ' . self::usage());
        }

        $email = strtolower($email);

        if (!$this->grant && User::findByEmail($email) === null) {
            return $this->fail("No user with email {$email}.");
        }

        $userId = User::setAdmin($email, $this->grant);
        $this->line(sprintf('%s admin access for %s (user #%d).', $this->grant ? 'Granted' : 'Revoked', $email, $userId));

        return 0;
    }
}
