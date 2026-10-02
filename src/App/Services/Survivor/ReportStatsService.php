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
 * statistics or (b) statistics and their account. Private reports (c) are never
 * counted. A withdrawn report is deleted, so it drops out of every figure the
 * moment they withdraw.
 *
 * Small numbers (Phase 2.1): the section's figures appear only once a school
 * has stats_min_reports (default 3) counted reports, and then EACH figure
 * needs that many contributing responses of its own: the average rating that
 * many ratings, each reason for not reporting that many people giving it,
 * each year that many reports. A figure short of it is null, and the page
 * says "Not enough responses yet" (or leaves a reason out) rather than show a
 * number that could describe one or two people. No count under the threshold
 * is ever returned.
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
     *   by_year: ?list<array{label: string, count: ?int}>,
     *   reported_pct: ?int, discouraged_pct: ?int,
     *   average_rating: ?float, rating_count: ?int,
     *   any_not_reported: bool, not_reported: ?int,
     *   top_reasons: list<array{key: string, label: string, count: int}>, reasons_left_out: bool,
     *   accounts: list<array{year: int, setting: ?string, category: string, text: string, evidence_badge: bool}>
     * }
     *   by_year: rows of years with enough reports, newest first, then one
     *   row grouping every other year (count null when that group is also
     *   short); null when no single year has enough.
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
        $byYear = []; // year => reports
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

        $enough = static fn (int $responses): bool => $responses >= $threshold;

        // Reasons: only those enough people gave, most common first, at most three.
        arsort($reasons);
        $labels = (array) Config::get('survivor_reports.not_reported_reasons', []);
        $topReasons = [];
        foreach ($reasons as $key => $reasonCount) {
            if ($enough($reasonCount) && count($topReasons) < 3) {
                $topReasons[] = ['key' => (string) $key, 'label' => (string) ($labels[$key] ?? $key), 'count' => $reasonCount];
            }
        }

        // Every report answers whether they reported to the school, so both
        // percentages have all of them contributing.
        return [
            'count' => $count,
            'threshold' => $threshold,
            'show_stats' => $enough($count),
            'by_year' => self::yearRows($byYear, $threshold),
            'reported_pct' => $enough($count) ? (int) round(100 * $reported / $count) : null,
            'discouraged_pct' => $enough($count) ? (int) round(100 * $discouraged / $count) : null,
            'average_rating' => $enough(count($ratings)) ? round(array_sum($ratings) / count($ratings), 1) : null,
            'rating_count' => $enough(count($ratings)) ? count($ratings) : null,
            'any_not_reported' => $notReported > 0,
            'not_reported' => $enough($notReported) ? $notReported : null,
            'top_reasons' => $topReasons,
            'reasons_left_out' => count(array_filter($reasons, static fn (int $n): bool => $n > 0)) > count($topReasons),
            'accounts' => $this->accounts($reports),
        ];
    }

    /**
     * Years with enough reports, newest first, and the rest as one row:
     * "Earlier years" when they all come before the years shown, otherwise
     * "Other years". One row per small year would let a reader add them back
     * up from the total.
     *
     * @param array<int, int> $byYear year => reports
     * @return ?list<array{label: string, count: ?int}>
     */
    private static function yearRows(array $byYear, int $threshold): ?array
    {
        krsort($byYear);
        $shown = array_filter($byYear, static fn (int $n): bool => $n >= $threshold);
        $rest = array_diff_key($byYear, $shown);

        if ($shown === []) {
            return null;
        }

        $rows = [];
        foreach ($shown as $year => $reports) {
            $rows[] = ['label' => (string) $year, 'count' => $reports];
        }

        if ($rest !== []) {
            $restTotal = array_sum($rest);
            $rows[] = [
                'label' => max(array_keys($rest)) < min(array_keys($shown)) ? 'Earlier years' : 'Other years',
                'count' => $restTotal >= $threshold ? $restTotal : null,
            ];
        }

        return $rows;
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
