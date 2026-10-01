<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Models\StatePage;
use Keel\Core\Activity;
use Keel\Core\Request;

/**
 * State pages. Publishing is gated on legal review: a page cannot be set to
 * published without statute text, resources, a reviewer and a review date. A
 * page left in needs_legal_review shows none of its legal fields publicly.
 */
class StatePageController extends AdminController
{
    public function index(Request $request): void
    {
        $this->view('admin.states.index', ['title' => 'State pages', 'pages' => StatePage::all()]);
    }

    public function create(Request $request): void
    {
        $this->view('admin.states.form', [
            'title' => 'New state page',
            'page' => null,
            'values' => ['status' => StatePage::STATUS_NEEDS_LEGAL_REVIEW],
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        [$values, $errors] = $this->validated($request, null);

        if ($errors !== []) {
            $this->invalid('admin.states.form', ['title' => 'New state page', 'page' => null, 'values' => $values, 'errors' => $errors]);

            return;
        }

        $id = StatePage::create($values + ['updated_by' => $this->adminId()]);
        Activity::log('state_page.created', 'StatePage', $id);
        $this->redirect('/admin/states/' . $id . '/edit?status=created');
    }

    public function edit(Request $request, string $id): void
    {
        $page = StatePage::find((int) $id) ?? $this->notFound();

        $this->view('admin.states.form', ['title' => 'Edit state page', 'page' => $page, 'values' => $page, 'errors' => []]);
    }

    public function update(Request $request, string $id): void
    {
        $page = StatePage::find((int) $id) ?? $this->notFound();
        [$values, $errors] = $this->validated($request, (int) $page['id']);

        if ($errors !== []) {
            $this->invalid('admin.states.form', ['title' => 'Edit state page', 'page' => $page, 'values' => $values, 'errors' => $errors]);

            return;
        }

        StatePage::update((int) $page['id'], $values + ['updated_by' => $this->adminId()]);
        Activity::log('state_page.updated', 'StatePage', (int) $page['id'], ['status' => $values['status']]);
        $this->redirect('/admin/states/' . (int) $page['id'] . '/edit?status=saved');
    }

    public function destroy(Request $request, string $id): void
    {
        $page = StatePage::find((int) $id) ?? $this->notFound();
        StatePage::delete((int) $page['id']);
        Activity::log('state_page.deleted', 'StatePage', (int) $page['id'], ['code' => $page['code']]);
        $this->redirect('/admin/states?status=deleted');
    }

    /** @return array{0: array, 1: array<string, string>} */
    private function validated(Request $request, ?int $exceptId): array
    {
        $values = [
            'code' => strtoupper($this->text($request, 'code', 2)),
            'name' => $this->text($request, 'name', 60),
            'status' => $this->text($request, 'status', 30),
            'statute_of_limitations' => $this->text($request, 'statute_of_limitations', 60000),
            'resources' => $this->text($request, 'resources', 60000),
            'legal_reviewed_by' => $this->text($request, 'legal_reviewed_by', 255),
            'legal_reviewed_on' => $this->text($request, 'legal_reviewed_on', 10),
        ];
        $errors = [];

        if (!preg_match('/^[A-Z]{2}$/', $values['code'])) {
            $errors['code'] = 'Enter the two-letter code.';
        } elseif (StatePage::codeTaken($values['code'], $exceptId)) {
            $errors['code'] = 'Another state page already uses this code.';
        }

        if ($values['name'] === '') {
            $errors['name'] = 'Enter the state name.';
        }

        if (!array_key_exists($values['status'], StatePage::STATUSES)) {
            $errors['status'] = 'Choose a status.';
        }

        if ($values['legal_reviewed_on'] !== '' && !self::validDate($values['legal_reviewed_on'])) {
            $errors['legal_reviewed_on'] = 'Enter a date as YYYY-MM-DD.';
        }

        if ($values['status'] === StatePage::STATUS_PUBLISHED) {
            foreach ([
                'statute_of_limitations' => 'Add the reviewed statute of limitations text before publishing.',
                'resources' => 'Add the reviewed state resources before publishing.',
                'legal_reviewed_by' => 'Record who completed the legal review before publishing.',
                'legal_reviewed_on' => 'Record the legal review date before publishing.',
            ] as $field => $message) {
                if ($values[$field] === '' && !isset($errors[$field])) {
                    $errors[$field] = $message;
                }
            }
        }

        foreach (['statute_of_limitations', 'resources', 'legal_reviewed_by', 'legal_reviewed_on'] as $field) {
            $values[$field] = $values[$field] === '' ? null : $values[$field];
        }

        return [$values, $errors];
    }
}
