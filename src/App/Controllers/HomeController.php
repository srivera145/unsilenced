<?php

namespace Keel\App\Controllers;

use Keel\App\Models\CleryStat;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\Core\Controller;
use Keel\Core\Request;

class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('home', [
            'metaDescription' => (string) Config::get('site.description'),
            'schoolCount' => School::countWithCleryData(),
            'cleryYears' => CleryStat::years(),
            'states' => Config::allJurisdictions(),
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
