<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\Core\Request;

/**
 * Reads and checks a report's answers, for the submit form and her edits.
 * Every answer must be one of the configured keys; anything else is dropped,
 * never stored. Error messages are plain and never repeat what she typed.
 */
final class ReportInput
{
    /** Which form step each field is on, so the form can open at the first problem. */
    public const STEPS = [
        'school_id' => 2,
        'incident_year' => 3, 'incident_season' => 3, 'setting' => 3,
        'perpetrator' => 4,
        'reported_to_school' => 5, 'school_channels' => 5, 'reported_to_police' => 5,
        'not_reported_reasons' => 5, 'school_outcomes' => 5, 'response_rating' => 5,
        'account' => 6, 'name_scan_confirmed' => 6,
        'consent' => 7,
        'evidence' => 8, 'evidence_attest' => 8,
        'email' => 9, 'attest' => 9, 'form' => 9,
    ];

    public const EARLIEST_YEAR = 1950;

    /**
     * @return array{values: array<string, mixed>, errors: array<string, string>, findings: list<array>, school: ?array}
     */
    public static function fromRequest(Request $request): array
    {
        $config = (array) Config::get('survivor_reports', []);
        $errors = [];

        $schoolId = self::int($request->input('school_id'));
        $school = $schoolId !== null ? School::find($schoolId) : null;
        if ($school === null) {
            $errors['school_id'] = 'Choose the school.';
        }

        $year = self::int($request->input('incident_year'));
        if ($year === null || $year < self::EARLIEST_YEAR || $year > (int) gmdate('Y')) {
            $errors['incident_year'] = 'Choose the year it happened. If you are not sure, choose your best guess.';
            $year = null;
        }

        $reported = (string) $request->input('reported_to_school', '');
        if (!in_array($reported, ['yes', 'no'], true)) {
            $errors['reported_to_school'] = 'Choose yes or no.';
        }
        $reportedToSchool = $reported === 'yes';

        $channels = self::keys($request->input('school_channels'), $config['school_channels'] ?? []);
        if ($reportedToSchool && $channels === []) {
            $errors['school_channels'] = 'Choose who you reported to at the school.';
        }

        $rating = self::int($request->input('response_rating'));
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            $rating = null;
        }

        $perpetrator = self::key($request->input('perpetrator'), $config['perpetrators'] ?? []);
        if ($perpetrator === null) {
            $errors['perpetrator'] = 'Choose one. "I would rather not say" is fine.';
        }

        $consent = self::key($request->input('consent'), $config['consents'] ?? []);
        if ($consent === null) {
            $errors['consent'] = 'Choose what we may do with your report.';
        }

        $rawAccount = str_replace("\r\n", "\n", (string) $request->input('account', ''));
        $account = trim($rawAccount);
        $maxAccount = (int) ($config['account_max_length'] ?? 5000);
        if (mb_strlen($account) > $maxAccount) {
            $errors['account'] = 'Your account is longer than ' . number_format($maxAccount) . ' characters. Please shorten it.';
            $account = mb_substr($account, 0, $maxAccount);
        }

        if ($consent === 'stats_and_account' && $account === '') {
            $errors['consent'] = 'To publish your account, write it in step 6, or choose another option here.';
        }

        $confirmed = in_array((string) $request->input('name_scan_confirmed', ''), ['1', 'on'], true);
        $findings = [];
        if ($account !== '') {
            $ignore = $school !== null ? (preg_split('/[^\p{L}\']+/u', (string) $school['name']) ?: []) : [];
            $findings = (new NameScanService())->scan($account, $ignore);
            if ($findings !== [] && !$confirmed) {
                $errors['name_scan_confirmed'] = 'Your account may include names or contact details. Remove them, or confirm you have checked.';
            }
        }

        $values = [
            'school_id' => $school !== null ? (int) $school['id'] : null,
            'incident_year' => $year,
            'incident_season' => self::key($request->input('incident_season'), $config['seasons'] ?? []),
            'setting' => self::key($request->input('setting'), $config['settings'] ?? []),
            'perpetrator' => $perpetrator,
            'reported_to_school' => $reportedToSchool ? 1 : 0,
            'reported_to_school_answer' => in_array($reported, ['yes', 'no'], true) ? $reported : null,
            'school_channels' => $reportedToSchool ? $channels : [],
            'reported_to_police' => self::key($request->input('reported_to_police'), $config['police'] ?? []),
            'not_reported_reasons' => $reportedToSchool ? [] : self::keys($request->input('not_reported_reasons'), $config['not_reported_reasons'] ?? []),
            'school_outcomes' => $reportedToSchool ? self::keys($request->input('school_outcomes'), $config['school_outcomes'] ?? []) : [],
            'response_rating' => $reportedToSchool ? $rating : null,
            'account' => $account === '' ? null : $account,
            'consent' => $consent,
            'name_scan_confirmed' => $findings !== [] && $confirmed ? 1 : 0,
        ];

        return ['values' => $values, 'errors' => $errors, 'findings' => $findings, 'school' => $school];
    }

    /** The first step with a problem. */
    public static function firstErrorStep(array $errors): int
    {
        $steps = array_map(static fn (string $field): int => self::STEPS[$field] ?? 9, array_keys($errors));

        return $steps === [] ? 1 : min($steps);
    }

    /** Values for re-showing the form from a stored report. */
    public static function fromReport(array $report, ?string $account): array
    {
        return [
            'school_id' => (int) $report['school_id'],
            'incident_year' => (int) $report['incident_year'],
            'incident_season' => $report['incident_season'],
            'setting' => $report['setting'],
            'perpetrator' => $report['perpetrator'],
            'reported_to_school' => (int) $report['reported_to_school'],
            'reported_to_school_answer' => (int) $report['reported_to_school'] === 1 ? 'yes' : 'no',
            'school_channels' => \Keel\App\Models\SurvivorReport::listValue($report['school_channels']),
            'reported_to_police' => $report['reported_to_police'],
            'not_reported_reasons' => \Keel\App\Models\SurvivorReport::listValue($report['not_reported_reasons']),
            'school_outcomes' => \Keel\App\Models\SurvivorReport::listValue($report['school_outcomes']),
            'response_rating' => $report['response_rating'] !== null ? (int) $report['response_rating'] : null,
            'account' => $account,
            'consent' => $report['consent'],
            'name_scan_confirmed' => (int) $report['name_scan_confirmed'],
        ];
    }

    private static function int(mixed $value): ?int
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? (int) $value : null;
    }

    private static function key(mixed $value, array $allowed): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && array_key_exists($value, $allowed) ? $value : null;
    }

    /** @return list<string> */
    private static function keys(mixed $values, array $allowed): array
    {
        $values = is_array($values) ? $values : [];
        $kept = [];
        foreach ($allowed as $key => $label) {
            if (in_array((string) $key, array_map('strval', $values), true)) {
                $kept[] = (string) $key;
            }
        }

        return $kept;
    }
}
