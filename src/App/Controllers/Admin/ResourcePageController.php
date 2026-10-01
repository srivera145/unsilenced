<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Models\ResourcePage;
use Keel\App\Support\Config;
use Keel\Core\Activity;
use Keel\Core\Request;

class ResourcePageController extends AdminController
{
    public function index(Request $request): void
    {
        $this->view('admin.resources.index', ['title' => 'Resource pages', 'pages' => ResourcePage::all()]);
    }

    public function create(Request $request): void
    {
        $this->view('admin.resources.form', [
            'title' => 'New resource page',
            'page' => null,
            'values' => ['is_published' => 0, 'sort_order' => 100],
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        [$values, $errors] = $this->validated($request, null);

        if ($errors !== []) {
            $this->invalid('admin.resources.form', ['title' => 'New resource page', 'page' => null, 'values' => $values, 'errors' => $errors]);

            return;
        }

        $id = ResourcePage::create($values + ['updated_by' => $this->adminId()]);
        Activity::log('resource_page.created', 'ResourcePage', $id);
        $this->redirect('/admin/resources/' . $id . '/edit?status=created');
    }

    public function edit(Request $request, string $id): void
    {
        $page = ResourcePage::find((int) $id) ?? $this->notFound();

        $this->view('admin.resources.form', ['title' => 'Edit resource page', 'page' => $page, 'values' => $page, 'errors' => []]);
    }

    public function update(Request $request, string $id): void
    {
        $page = ResourcePage::find((int) $id) ?? $this->notFound();
        [$values, $errors] = $this->validated($request, (int) $page['id']);

        if ($errors !== []) {
            $this->invalid('admin.resources.form', ['title' => 'Edit resource page', 'page' => $page, 'values' => $values, 'errors' => $errors]);

            return;
        }

        ResourcePage::update((int) $page['id'], $values + ['updated_by' => $this->adminId()]);
        Activity::log('resource_page.updated', 'ResourcePage', (int) $page['id']);
        $this->redirect('/admin/resources/' . (int) $page['id'] . '/edit?status=saved');
    }

    public function destroy(Request $request, string $id): void
    {
        $page = ResourcePage::find((int) $id) ?? $this->notFound();
        ResourcePage::delete((int) $page['id']);
        Activity::log('resource_page.deleted', 'ResourcePage', (int) $page['id'], ['slug' => $page['slug']]);
        $this->redirect('/admin/resources?status=deleted');
    }

    /** @return array{0: array, 1: array<string, string>} */
    private function validated(Request $request, ?int $exceptId): array
    {
        $values = [
            'slug' => strtolower($this->text($request, 'slug', 100)),
            'title' => $this->text($request, 'title', 160),
            'browser_title' => $this->text($request, 'browser_title', 160),
            'summary' => $this->text($request, 'summary', 300),
            'body' => $this->text($request, 'body', 60000),
            'sort_order' => $this->intOrNull($request, 'sort_order') ?? 0,
            'is_published' => $this->checked($request, 'is_published') ? 1 : 0,
        ];
        $errors = [];

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $values['slug'])) {
            $errors['slug'] = 'Use lowercase letters, numbers and single hyphens.';
        } elseif (ResourcePage::slugTaken($values['slug'], $exceptId)) {
            $errors['slug'] = 'Another page already uses this URL.';
        }

        if ($values['title'] === '') {
            $errors['title'] = 'Enter a heading.';
        }

        // The tab title shows in browser history and on shared screens.
        $tabTitle = $values['browser_title'] !== '' ? $values['browser_title'] : $values['title'];
        $tabField = $values['browser_title'] !== '' ? 'browser_title' : 'title';
        foreach ((array) Config::get('neutral_title_blocklist', []) as $word) {
            if ($tabTitle !== '' && str_contains(mb_strtolower($tabTitle), (string) $word)) {
                $errors[$tabField] = "The browser tab would read \"{$tabTitle}\", which contains \"{$word}\". Tab titles show in history and on shared screens: "
                    . ($tabField === 'title' ? 'set a neutral tab title below, such as "Keeping records".' : 'choose a neutral wording.');
                break;
            }
        }

        $values['browser_title'] = $values['browser_title'] === '' ? null : $values['browser_title'];

        if ($values['summary'] === '') {
            $errors['summary'] = 'Enter a one-sentence summary for search results.';
        }

        if ($values['body'] === '') {
            $errors['body'] = 'Enter the page text.';
        }

        return [$values, $errors];
    }
}
