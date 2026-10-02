<?php

namespace Keel\App\Controllers;

use Keel\App\Models\CleryStat;
use Keel\App\Models\School;
use Keel\App\Services\Survivor\ReportStatsService;
use Keel\App\Support\Config;
use Keel\App\Support\Submissions;
use Keel\Core\Controller;
use Keel\Core\Env;
use Keel\Core\Request;

class HomeController extends Controller
{
    public function index(Request $request): void
    {
        // Phase 2: the national total of counted survivor reports, once there
        // are homepage_min_reports of them, and only while submissions are open.
        // Never below the per-figure minimum, whatever the setting says.
        $survivorTotal = null;
        if (Submissions::enabled()) {
            $total = (new ReportStatsService())->nationalTotal();
            $minimum = max((int) Config::get('survivor_reports.homepage_min_reports', 25), ReportStatsService::threshold());
            $survivorTotal = $total >= $minimum ? $total : null;
        }

        $this->view('home', [
            'metaDescription' => (string) Config::get('site.description'),
            'schoolCount' => School::countWithCleryData(),
            'cleryYears' => CleryStat::years(),
            'states' => Config::allJurisdictions(),
            'submissionsOpen' => Submissions::enabled(),
            'survivorTotal' => $survivorTotal,
        ]);
    }

    /**
     * How to tell us about an error. A plain page with an email address from
     * .env (CORRECTIONS_EMAIL): no form, nothing collected or stored here.
     */
    public function corrections(Request $request): void
    {
        $email = trim((string) Env::get('CORRECTIONS_EMAIL', ''));

        $this->view('corrections', [
            'title' => 'Corrections',
            'metaDescription' => 'How schools, journalists and the public can tell Unsilenced about an error in its data or records, and what we do with it.',
            'correctionsEmail' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null,
        ]);
    }

    public function methodology(Request $request): void
    {
        $this->view('methodology', [
            'title' => 'How we get our data',
            'metaDescription' => 'Where Unsilenced data comes from: Clery Act crime statistics from the U.S. Department of Education and enrollment from IPEDS, how rates and comparisons are calculated, and the limits of the data.',
            'cleryYears' => CleryStat::years(),
        ]);
    }
}
