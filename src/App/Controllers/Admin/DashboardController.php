<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Models\AccountabilityItem;
use Keel\App\Models\CleryStat;
use Keel\App\Models\ImportRun;
use Keel\App\Models\School;
use Keel\App\Models\StatePage;
use Keel\Core\Request;

class DashboardController extends AdminController
{
    public function index(Request $request): void
    {
        $states = StatePage::all();

        $this->view('admin.dashboard', [
            'title' => 'Admin',
            'counts' => [
                'schools' => School::count(),
                'schools_with_clery' => School::countWithCleryData(),
                'clery_rows' => CleryStat::count(),
                'clery_years' => CleryStat::years(),
                'items' => AccountabilityItem::count(),
                'states_published' => count(array_filter($states, [StatePage::class, 'isPublished'])),
                'states_total' => count($states),
            ],
            'runs' => ImportRun::recent(5),
        ]);
    }
}
