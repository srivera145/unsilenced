<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Models\SurvivorReport;
use Keel\App\Support\Config;
use Keel\Core\Database;

/**
 * What a school page shows under "What survivors have told us", and the
 * national total on the home page.
 *
 * Only approved reports count, and only those whose author chose (a)
 * statistics or (b) statistics and her account. Private reports (c) are never
 * counted. A withdrawn report is deleted, so it drops out of every figure the
 * moment she withdraws.
 *
 * Figures appear only once a school has stats_min_reports (default 3): with
 * fewer, a percentage could describe one person.
 */
final class ReportStatsService
{
    public static function threshold(): int
    {
        return max(1, (int) Config::get('survivor_reports.stats_min_reports', 3));
    }

    /**
     * @return array{
     *   count: int, threshold: int, show_stats: bool,
     *   by_year: array<int, int>, reported_pct: ?int, discouraged_pct: ?int,
     *   average_rating: ?float, rating_count: int,
     *   not_reported: int, top_reasons: list<array{key: string, label: string, count: int}>,
     *   accounts: list<array{year: int, setting: ?string, category: string, text: string, evidence_badge: bool}>
     * }
     */
    public function forSchool(int $schoolId): array
    {
        $placeholders = implode(', ', array_fill(0, count(SurvivorReport::COUNTED_CONSENTS), '?'));
        $statement = Database::connection()->prepare(
            "SELECT * FROM survivor_reports
             WHERE school_id = ? AND status = 'approved' AND consent IN ({$placeholders})
             ORDER BY approved_at DESC, id DESC"
        );
        $statement->execute([$schoolId, ...SurvivorReport::COUNTED_CONSENTS]);
        $reports = $statement->fetchAll();

        $count = count($reports);
        $threshold = self::threshold();
        $byYear = [];
        $reported = 0;
        $discouraged = 0;
        $ratings = [];
        $notReported = 0;
        $reasons = [];

        foreach ($reports as $report) {
            $year = (int) $report['incident_year'];
            $byYear[$year] = ($byYear[$year] ?? 0) + 1;

            $outcomes = SurvivorReport::listValue($report['school_outcomes']);
            $reasonList = SurvivorReport::listValue($report['not_reported_reasons']);

            if ((int) $report['reported_to_school'] === 1) {
                $reported++;
                if ($report['response_rating'] !== null) {
                    $ratings[] = (int) $report['response_rating'];
                }
            } else {
                $notReported++;
                foreach ($reasonList as $reason) {
                    $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                }
            }

            // Discouraged or pressured after reporting, or discouraged from reporting at all.
            if (array_intersect($outcomes, ['discouraged', 'pressured_quiet']) !== [] || in_array('school_discouraged', $reasonList, true)) {
                $discouraged++;
            }
        }

        krsort($byYear);
        arsort($reasons);
        $labels = (array) Config::get('survivor_reports.not_reported_reasons', []);
        $topReasons = [];
        foreach (array_slice($reasons, 0, 3, true) as $key => $reasonCount) {
            $topReasons[] = ['key' => (string) $key, 'label' => (string) ($labels[$key] ?? $key), 'count' => $reasonCount];
        }

        return [
            'count' => $count,
            'threshold' => $threshold,
            'show_stats' => $count >= $threshold,
            'by_year' => $byYear,
            'reported_pct' => $count > 0 ? (int) round(100 * $reported / $count) : null,
            'discouraged_pct' => $count > 0 ? (int) round(100 * $discouraged / $count) : null,
            'average_rating' => $ratings === [] ? null : round(array_sum($ratings) / count($ratings), 1),
            'rating_count' => count($ratings),
            'not_reported' => $notReported,
            'top_reasons' => $topReasons,
            'accounts' => $this->accounts($reports),
        ];
    }

    /** Approved reports that may be counted, nationwide. */
    public function nationalTotal(): int
    {
        $placeholders = implode(', ', array_fill(0, count(SurvivorReport::COUNTED_CONSENTS), '?'));
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM survivor_reports WHERE status = 'approved' AND consent IN ({$placeholders})");
        $statement->execute(SurvivorReport::COUNTED_CONSENTS);

        return (int) $statement->fetchColumn();
    }

    /**
     * Published accounts (consent b): year, setting and category, and the
     * admin's edited text. Never the season, a date or anything else.
     */
    private function accounts(array $reports): array
    {
        $settings = (array) Config::get('survivor_reports.settings', []);
        $categories = (array) Config::get('survivor_reports.perpetrator_public', []);
        $accounts = [];

        foreach ($reports as $report) {
            if ($report['consent'] !== 'stats_and_account') {
                continue;
            }

            $text = trim((string) SurvivorReport::published($report));
            if ($text === '') {
                continue;
            }

            $accounts[] = [
                'year' => (int) $report['incident_year'],
                'setting' => $report['setting'] !== null ? (string) ($settings[$report['setting']] ?? $report['setting']) : null,
                'category' => (string) ($categories[$report['perpetrator']] ?? 'Not given'),
                'text' => $text,
                'evidence_badge' => $report['evidence_reviewed'] === 'yes' && \Keel\App\Models\EvidenceFile::hasReviewedFile((int) $report['id']),
            ];
        }

        return $accounts;
    }
}
