<?php

declare(strict_types=1);

namespace Tests\Support;

use Keel\App\Models\School;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseKeyService;
use Keel\App\Services\Survivor\ProofOfWork;
use Keel\Core\Database;

/**
 * Sending reports the way the browser does, and making reports directly for
 * tests that are about what happens after. Use in a TestCase after
 * enableSubmissions() and importFixtures().
 */
trait SurvivorHelpers
{
    protected const ACCOUNT = 'In my second year I went to the Title IX office. They told me to wait and nothing happened for months.';

    protected function school(int $unitid = 990001): array
    {
        return School::findByUnitid($unitid) ?? throw new \RuntimeException('Import the fixtures first.');
    }

    /** The answers a complete form sends, before overrides. */
    protected function answers(array $overrides = []): array
    {
        return array_merge([
            'school_id' => (string) $this->school()['id'],
            'incident_year' => '2023',
            'incident_season' => 'fall',
            'setting' => 'residence_hall',
            'perpetrator' => 'fellow_student',
            'reported_to_school' => 'yes',
            'school_channels' => ['title_ix'],
            'school_outcomes' => ['no_response', 'discouraged'],
            'response_rating' => '1',
            'reported_to_police' => 'no',
            'account' => self::ACCOUNT,
            'consent' => 'stats_and_account',
            'attest' => '1',
            'email' => '',
        ], $overrides);
    }

    /**
     * GET /submit for a challenge, solve it, POST the form.
     *
     * @param list<array{0: string, 1: string}> $files [path, name]
     * @return array{response: TestResponse, key: ?string}
     */
    protected function submitReport(array $overrides = [], array $files = []): array
    {
        $this->get('/submit');
        $challenge = ProofOfWork::challenge();
        $data = $this->answers($overrides) + [
            '_csrf' => $this->csrfToken(),
            'pow_challenge' => $challenge['challenge'],
            'pow_nonce' => ProofOfWork::solve($challenge['challenge'], $challenge['bits']),
        ];
        if ($files !== []) {
            $data['evidence_attest'] ??= '1';
        }

        $response = $files === [] ? $this->post('/submit', $data) : $this->postWithFiles('/submit', $data, ['evidence' => $files]);

        return ['response' => $response, 'key' => self::keyFrom($response->body)];
    }

    /** The six words from the key page, or null. */
    protected static function keyFrom(string $html): ?string
    {
        if (!preg_match('#<ol class="case-key"[^>]*>(.*?)</ol>#s', $html, $match)) {
            return null;
        }
        preg_match_all('#<li>([a-z]+)</li>#', $match[1], $words);

        return count($words[1]) === 6 ? implode(' ', $words[1]) : null;
    }

    protected function openReport(string $key): TestResponse
    {
        return $this->post('/my-report', ['_csrf' => $this->csrfToken(), 'case_key' => $key]);
    }

    /**
     * A report made directly, for tests of what happens after submission.
     *
     * @return array{case_id: int, report_id: int, key: string}
     */
    protected function makeReport(array $answers = [], string $status = 'submitted', ?string $email = null): array
    {
        $keys = new CaseKeyService();
        $key = $keys->generate();
        $caseId = SurvivorCase::create($keys->lookupId($key), $keys->hash($key), $email);
        $values = array_merge([
            'school_id' => (int) $this->school()['id'],
            'consent' => 'stats',
            'incident_year' => 2023,
            'incident_season' => null,
            'setting' => 'residence_hall',
            'perpetrator' => 'fellow_student',
            'reported_to_school' => 1,
            'school_channels' => ['title_ix'],
            'reported_to_police' => null,
            'not_reported_reasons' => [],
            'school_outcomes' => [],
            'response_rating' => null,
            'account' => null,
            'name_scan_confirmed' => 0,
        ], $answers);
        $reportId = SurvivorReport::create($caseId, $values, $status);

        if ($status === 'approved') {
            SurvivorReport::write($reportId, ['approved_at' => gmdate('Y-m-d H:i:s')]);
        }

        return ['case_id' => $caseId, 'report_id' => $reportId, 'key' => $key];
    }

    /** Rows anywhere that belong to a case. */
    protected function rowsForCase(int $caseId, int $reportId, array $fileIds): array
    {
        $connection = Database::connection();
        $count = static function (string $sql, array $params) use ($connection): int {
            $statement = $connection->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn();
        };
        $in = $fileIds === [] ? '0' : implode(',', array_map('intval', $fileIds));

        return [
            'survivor_cases' => $count('SELECT COUNT(*) FROM survivor_cases WHERE id = ?', [$caseId]),
            'survivor_reports' => $count('SELECT COUNT(*) FROM survivor_reports WHERE id = ? OR case_id = ?', [$reportId, $caseId]),
            'evidence_files' => $count("SELECT COUNT(*) FROM evidence_files WHERE report_id = ? OR id IN ({$in})", [$reportId]),
            'share_links' => $count('SELECT COUNT(*) FROM share_links WHERE case_id = ?', [$caseId]),
            'share_link_files' => $count("SELECT COUNT(*) FROM share_link_files WHERE evidence_file_id IN ({$in})", []),
            'moderation_events' => $count('SELECT COUNT(*) FROM moderation_events WHERE report_id = ?', [$reportId]),
            'activity_log' => $count("SELECT COUNT(*) FROM activity_log WHERE (subject_type = 'SurvivorReport' AND subject_id = ?) OR (subject_type = 'EvidenceFile' AND subject_id IN ({$in}))", [$reportId]),
        ];
    }

    /** Every file under the test vault. */
    protected function vaultFiles(): array
    {
        $root = (string) self::$vaultPath;
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
