<?php

namespace Keel\App\Controllers;

use Keel\App\Models\ResourcePage;
use Keel\Core\Controller;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;

class ResourceController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('resources.index', [
            'title' => 'Resources',
            'metaDescription' => 'Free, confidential help after sexual assault, your options in plain language, and how to save evidence.',
            'pages' => ResourcePage::published(),
        ]);
    }

    public function show(Request $request, string $slug): void
    {
        $page = ResourcePage::findPublishedBySlug($slug);

        if ($page === null) {
            ErrorHandler::render(404);
        }

        $this->view('resources.show', [
            // The tab title, which may differ from the heading: see browser_title.
            'title' => (string) (($page['browser_title'] ?? '') !== '' ? $page['browser_title'] : $page['title']),
            'metaDescription' => (string) $page['summary'],
            'page' => $page,
            'navCurrent' => match ($slug) {
                'get-help' => 'help',
                'your-options' => 'options',
                default => null,
            },
        ]);
    }
}
