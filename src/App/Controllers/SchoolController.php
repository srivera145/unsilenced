<?php

namespace Keel\App\Controllers;

use Keel\App\Models\AccountabilityItem;
use Keel\App\Models\School;
use Keel\App\Services\SchoolProfileService;
use Keel\App\Support\Config;
use Keel\Core\Controller;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;

class SchoolController extends Controller
{
    /** /schools?q=&state=&page= */
    public function index(Request $request): void
    {
        $query = mb_substr(trim((string) $request->input('q', '')), 0, 120);
        $state = strtoupper(trim((string) $request->input('state', '')));

        if ($state !== '' && Config::jurisdictionName($state) === null) {
            $state = '';
        }

        $this->renderList($request, $query, $state === '' ? null : $state);
    }

    /** /schools/{state} */
    public function state(Request $request, string $state): void
    {
        $code = strtoupper($state);

        if (Config::jurisdictionName($code) === null || $state !== strtolower($state)) {
            ErrorHandler::render(404);
        }

        $this->renderList($request, mb_substr(trim((string) $request->input('q', '')), 0, 120), $code);
    }

    /** /schools/{state}/{slug} */
    public function show(Request $request, string $state, string $slug): void
    {
        $school = School::findByStateAndSlug($state, rawurldecode($slug));

        if ($school === null || $state !== strtolower($state)) {
            ErrorHandler::render(404);
        }

        $profile = (new SchoolProfileService())->build($school);
        $stateName = Config::jurisdictionName((string) $school['state']) ?? (string) $school['state'];

        $this->view('schools.show', [
            'title' => (string) $school['name'],
            'metaDescription' => sprintf(
                'Clery Act sexual-violence statistics, rates per 1,000 students and public accountability records for %s in %s, %s.',
                $school['name'],
                $school['city'] ?? $stateName,
                $stateName
            ),
            'school' => $school,
            'stateName' => $stateName,
            'profile' => $profile,
            'items' => AccountabilityItem::publishedForSchool((int) $school['id']),
        ]);
    }

    private function renderList(Request $request, string $query, ?string $state): void
    {
        $page = max(1, (int) $request->input('page', 1));
        $results = ($query !== '' || $state !== null)
            ? School::search($query, $state, $page)
            : ['rows' => [], 'total' => 0];

        $stateName = $state !== null ? Config::jurisdictionName($state) : null;

        $this->view('schools.index', [
            'title' => $stateName !== null ? 'Schools in ' . $stateName : 'Schools',
            'metaDescription' => $stateName !== null
                ? "Colleges and universities in {$stateName}, with Clery Act sexual-violence statistics and accountability records."
                : 'Search every U.S. college by name, city or state for Clery Act sexual-violence statistics and accountability records.',
            'query' => $query,
            'state' => $state,
            'stateName' => $stateName,
            'page' => $page,
            'results' => $results,
            'perPage' => School::PER_PAGE,
            'states' => Config::allJurisdictions(),
            'stateCounts' => ($query === '' && $state === null) ? School::countsByState() : [],
        ]);
    }
}
