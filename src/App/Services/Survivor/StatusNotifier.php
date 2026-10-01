<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Models\SurvivorCase;
use Keel\Core\Env;
use Keel\Core\Mailer;

/**
 * The optional status email. It says only that there is an update and where
 * to read it: no school, no status, no note, nothing from the report, so an
 * inbox someone else can see reveals as little as possible. The subject is
 * neutral too. Sent directly, not queued: a queued job would keep the address
 * in the jobs table.
 */
final class StatusNotifier
{
    public const SUBJECT = 'An update is ready';

    /** @return bool whether an email was sent */
    public function notify(int $caseId): bool
    {
        $case = SurvivorCase::find($caseId);
        $email = $case !== null ? SurvivorCase::email($case) : null;

        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $url = htmlspecialchars(rtrim((string) Env::get('APP_URL', ''), '/') . '/my-report', ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
            <p>Hello,</p>
            <p>There is an update on the page you asked us to tell you about.</p>
            <p>To read it, go to <a href="{$url}">{$url}</a> and enter your six-word key.</p>
            <p>If you did not expect this email, you can ignore it.</p>
            HTML;

        return Mailer::send($email, '', self::SUBJECT, $body);
    }
}
