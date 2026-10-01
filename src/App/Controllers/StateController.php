<?php

namespace Keel\App\Controllers;

use Keel\App\Models\School;
use Keel\App\Models\StatePage;
use Keel\Core\Controller;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;

class StateController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('states.index', [
            'title' => 'States',
            'metaDescription' => 'State-by-state statutes of limitations and local resources for survivors of sexual assault, added as each state completes legal review.',
            'pages' => StatePage::all(),
            'schoolCounts' => School::countsByState(),
        ]);
    }

    public function show(Request $request, string $code): void
    {
        $page = $code === strtolower($code) ? StatePage::findByCode($code) : null;

        if ($page === null) {
            ErrorHandler::render(404);
        }

        $this->view('states.show', [
            'title' => (string) $page['name'],
            'metaDescription' => StatePage::isPublished($page)
                ? "Statute of limitations and resources for survivors of sexual assault in {$page['name']}."
                : "Resources for survivors of sexual assault in {$page['name']}. State-specific legal information is coming soon.",
            'page' => $page,
            'schoolCount' => School::countsByState()[(string) $page['code']] ?? 0,
            'navCurrent' => 'states',
        ]);
    }
}
