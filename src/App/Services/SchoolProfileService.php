<?php

namespace Keel\App\Services;

use Keel\App\Models\CleryStat;
use Keel\App\Support\Config;

/**
 * Everything a school page says about its numbers, computed in one place so the
 * page, the tests and the methodology page agree.
 *
 * Totals add on_campus, noncampus and public_property. On-campus student
 * housing is reported separately by schools but is already inside on_campus,
 * so it is shown as its own column and never added again.
 */
class SchoolProfileService
{
    /**
     * @return array{
     *   years: list<int>,
     *   latest_year: ?int,
     *   by_year: array<int, array{locations: array<string, array<string, ?int>>, totals: array<string, ?int>, groups: array<string, ?int>}>,
     *   rates: array<int, array<string, ?float>>,
     *   comparison: ?array,
     *   context_years: list<int>,
     *   series: array<string, array<int, ?int>>
     * }
     */
    public function build(array $school): array
    {
        $rows = CleryStat::forSchool((int) $school['id']);
        $groups = (array) Config::get('offense_groups', []);
        $enrollment = isset($school['enrollment']) && $school['enrollment'] !== null ? (int) $school['enrollment'] : null;

        $byYear = [];
        foreach ($rows as $row) {
            $year = (int) $row['year'];
            $byYear[$year]['locations'][(string) $row['location']] = self::offenseValues($row);
        }

        ksort($byYear);

        foreach ($byYear as $year => &$data) {
            $data['totals'] = self::totals($data['locations']);
            $data['groups'] = [];
            foreach ($groups as $key => $group) {
                $data['groups'][$key] = self::sumNullable(array_map(
                    static fn (string $offense) => $data['totals'][$offense] ?? null,
                    (array) $group['offenses']
                ));
            }
        }
        unset($data);

        $years = array_keys($byYear);
        $latestYear = $years === [] ? null : max($years);

        $rates = [];
        foreach ($byYear as $year => $data) {
            foreach (array_keys($groups) as $key) {
                $rates[$year][$key] = self::rate($data['groups'][$key], $enrollment);
            }
        }

        $threshold = (int) Config::get('context_note_min_enrollment', 5000);
        $contextYears = [];
        if ($enrollment !== null && $enrollment >= $threshold) {
            foreach ($byYear as $year => $data) {
                if (($data['totals']['rape'] ?? null) === 0) {
                    $contextYears[] = $year;
                }
            }
        }

        $series = [];
        foreach (CleryStat::OFFENSES as $offense) {
            foreach ($byYear as $year => $data) {
                $series[$offense][$year] = $data['totals'][$offense] ?? null;
            }
        }

        return [
            'years' => $years,
            'latest_year' => $latestYear,
            'by_year' => $byYear,
            'rates' => $rates,
            'comparison' => $latestYear !== null && $enrollment !== null && $enrollment > 0
                ? $this->comparison($school, $latestYear, $rates[$latestYear])
                : null,
            'context_years' => $contextYears,
            'series' => $series,
        ];
    }

    /**
     * The school's rate beside the pooled rate for its state and for schools of
     * similar size nationally, for one year.
     */
    public function comparison(array $school, int $year, array $schoolRates): array
    {
        $groups = array_map(static fn (array $group): array => (array) $group['offenses'], (array) Config::get('offense_groups', []));
        $band = self::sizeBand((int) $school['enrollment']);

        $state = CleryStat::pooledTotals($year, $groups, ['state' => (string) $school['state']]);
        $peer = $band === null ? null : CleryStat::pooledTotals($year, $groups, ['min' => $band['min'], 'max' => $band['max']]);

        $result = ['year' => $year, 'band' => $band, 'groups' => []];
        $minSchools = max(1, (int) Config::get('comparison_min_schools', 3));

        foreach (array_keys($groups) as $key) {
            $stateSchools = $state[$key]['schools'];
            $peerSchools = $peer[$key]['schools'] ?? 0;

            $result['groups'][$key] = [
                'school' => $schoolRates[$key] ?? null,
                'state' => $stateSchools >= $minSchools ? self::rate($state[$key]['total'], $state[$key]['enrollment']) : null,
                'state_schools' => $stateSchools,
                'peer' => $peer !== null && $peerSchools >= $minSchools ? self::rate($peer[$key]['total'], $peer[$key]['enrollment']) : null,
                'peer_schools' => $peerSchools,
            ];
        }

        return $result;
    }

    /** @return array{min: int, max: ?int, label: string}|null */
    public static function sizeBand(int $enrollment): ?array
    {
        foreach ((array) Config::get('size_bands', []) as $band) {
            $max = $band['max'] ?? null;
            if ($enrollment >= (int) $band['min'] && ($max === null || $enrollment <= (int) $max)) {
                return ['min' => (int) $band['min'], 'max' => $max === null ? null : (int) $max, 'label' => (string) $band['label']];
            }
        }

        return null;
    }

    public static function rate(?int $count, ?int $enrollment): ?float
    {
        if ($count === null || $enrollment === null || $enrollment <= 0) {
            return null;
        }

        return $count / $enrollment * 1000;
    }

    /**
     * "higher than", "lower than" or "about the same as": within 10% counts as
     * about the same, so rounding noise is not described as a difference.
     */
    public static function compareWords(?float $value, ?float $reference): ?string
    {
        if ($value === null || $reference === null) {
            return null;
        }

        if ($reference == 0.0) {
            return $value == 0.0 ? 'the same as' : 'higher than';
        }

        $ratio = $value / $reference;

        return match (true) {
            $ratio > 1.1 => 'higher than',
            $ratio < 0.9 => 'lower than',
            default => 'about the same as',
        };
    }

    /** @return array<string, ?int> */
    private static function offenseValues(array $row): array
    {
        $values = [];
        foreach (CleryStat::OFFENSES as $offense) {
            $values[$offense] = $row[$offense] === null ? null : (int) $row[$offense];
        }

        return $values;
    }

    /**
     * @param array<string, array<string, ?int>> $locations
     * @return array<string, ?int>
     */
    private static function totals(array $locations): array
    {
        $totals = [];
        foreach (CleryStat::OFFENSES as $offense) {
            $totals[$offense] = self::sumNullable(array_map(
                static fn (string $location) => $locations[$location][$offense] ?? null,
                CleryStat::TOTAL_LOCATIONS
            ));
        }

        return $totals;
    }

    /** Null only when every value is null; otherwise nulls count as nothing. */
    private static function sumNullable(array $values): ?int
    {
        $present = array_filter($values, static fn ($value): bool => $value !== null);

        return $present === [] ? null : (int) array_sum($present);
    }
}
