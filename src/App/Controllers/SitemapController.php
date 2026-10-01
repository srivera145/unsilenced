<?php

namespace Keel\App\Controllers;

use Keel\App\Models\ResourcePage;
use Keel\App\Models\School;
use Keel\App\Models\StatePage;
use Keel\Core\Controller;
use Keel\Core\Env;
use Keel\Core\Request;
use Keel\Core\Response;
use Keel\Core\Router;

/**
 * /sitemap.xml: the routes flagged ['sitemap' => true], plus every school,
 * every state's school list and state page, and every published resource page,
 * read from the database at request time. One file holds up to 50,000 URLs,
 * which covers every IPEDS institution with room to spare.
 */
class SitemapController extends Controller
{
    public function index(Request $request): never
    {
        $baseUrl = $this->baseUrl();
        $entries = [];

        foreach ((Router::current()?->publicPages() ?? []) as $page) {
            $uri = (string) ($page['uri'] ?? '');
            if ($uri !== '' && !str_contains($uri, '{')) {
                $entries[$uri] = null;
            }
        }

        foreach (ResourcePage::published() as $page) {
            $entries['/resources/' . $page['slug']] = (string) $page['updated_at'];
        }

        foreach (StatePage::all() as $page) {
            $entries['/states/' . strtolower((string) $page['code'])] = (string) $page['updated_at'];
        }

        foreach (array_keys(School::countsByState()) as $state) {
            $entries['/schools/' . strtolower($state)] = null;
        }

        foreach (School::allForSitemap() as $school) {
            $entries[School::path($school)] = (string) $school['updated_at'];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($entries as $path => $updatedAt) {
            $xml .= '  <url><loc>' . htmlspecialchars($baseUrl . ($path === '/' ? '/' : $path), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>';
            if ($updatedAt !== null && ($timestamp = strtotime($updatedAt)) !== false) {
                $xml .= '<lastmod>' . gmdate('Y-m-d', $timestamp) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }

        $xml .= "</urlset>\n";

        Response::raw($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function baseUrl(): string
    {
        $baseUrl = trim((string) Env::get('APP_URL', ''));

        return $baseUrl !== '' ? rtrim($baseUrl, '/') : 'http://localhost';
    }
}
