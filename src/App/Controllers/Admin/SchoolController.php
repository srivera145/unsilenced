<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Models\AccountabilityItem;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\Core\Activity;
use Keel\Core\Request;

class SchoolController extends AdminController
{
    public function index(Request $request): void
    {
        $query = mb_substr(trim((string) $request->input('q', '')), 0, 120);
        $page = max(1, (int) $request->input('page', 1));

        $this->view('admin.schools.index', [
            'title' => 'Schools',
            'query' => $query,
            'page' => $page,
            'results' => School::search($query, null, $page, 50, false),
            'perPage' => 50,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('admin.schools.form', [
            'title' => 'New school',
            'school' => null,
            'values' => ['control' => '', 'state' => ''],
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        [$values, $errors] = $this->validated($request, null);

        if ($errors !== []) {
            $this->invalid('admin.schools.form', ['title' => 'New school', 'school' => null, 'values' => $values, 'errors' => $errors]);

            return;
        }

        $id = School::create($values);
        Activity::log('school.created', 'School', $id);
        $this->redirect('/admin/schools/' . $id . '/edit?status=created');
    }

    public function edit(Request $request, string $id): void
    {
        $school = School::find((int) $id) ?? $this->notFound();

        $this->view('admin.schools.form', [
            'title' => 'Edit school',
            'school' => $school,
            'values' => $school,
            'errors' => [],
            'items' => AccountabilityItem::adminList((int) $school['id']),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $school = School::find((int) $id) ?? $this->notFound();
        [$values, $errors] = $this->validated($request, (int) $school['id']);

        if ($errors !== []) {
            $this->invalid('admin.schools.form', [
                'title' => 'Edit school',
                'school' => $school,
                'values' => $values,
                'errors' => $errors,
                'items' => AccountabilityItem::adminList((int) $school['id']),
            ]);

            return;
        }

        School::update((int) $school['id'], $values);
        Activity::log('school.updated', 'School', (int) $school['id']);
        $this->redirect('/admin/schools/' . (int) $school['id'] . '/edit?status=saved');
    }

    public function destroy(Request $request, string $id): void
    {
        $school = School::find((int) $id) ?? $this->notFound();

        // Survivor reports belong to their authors: only they can withdraw them.
        if (\Keel\App\Models\SurvivorReport::countForSchool((int) $school['id']) > 0) {
            $this->redirect('/admin/schools/' . (int) $school['id'] . '/edit?status=has_reports');
        }

        School::delete((int) $school['id']);
        Activity::log('school.deleted', 'School', (int) $school['id'], ['unitid' => (int) $school['unitid']]);
        $this->redirect('/admin/schools?status=deleted');
    }

    /** @return array{0: array, 1: array<string, string>} */
    private function validated(Request $request, ?int $exceptId): array
    {
        $values = [
            'unitid' => $this->intOrNull($request, 'unitid'),
            'name' => $this->text($request, 'name', 255),
            'slug' => strtolower($this->text($request, 'slug', 191)),
            'city' => $this->text($request, 'city', 120),
            'state' => strtoupper($this->text($request, 'state', 2)),
            'control' => $this->text($request, 'control', 30),
            'enrollment' => $this->intOrNull($request, 'enrollment'),
            'enrollment_year' => $this->intOrNull($request, 'enrollment_year'),
        ];
        $errors = [];

        if ($values['unitid'] === null || $values['unitid'] < 1 || $values['unitid'] > 99999999) {
            $errors['unitid'] = 'Enter the IPEDS UNITID, a whole number.';
        } else {
            $existing = School::findByUnitid($values['unitid']);
            if ($existing !== null && (int) $existing['id'] !== $exceptId) {
                $errors['unitid'] = 'Another school already has this UNITID.';
            }
        }

        if ($values['name'] === '') {
            $errors['name'] = 'Enter the school name.';
        }

        if (Config::jurisdictionName($values['state']) === null) {
            $errors['state'] = 'Choose a state.';
        }

        if ($values['control'] !== '' && !array_key_exists($values['control'], (array) Config::get('controls', []))) {
            $errors['control'] = 'Choose a type from the list.';
        }

        if (trim((string) $request->input('enrollment', '')) !== '' && $values['enrollment'] === null) {
            $errors['enrollment'] = 'Enrollment must be a whole number.';
        }

        if ($values['enrollment_year'] !== null && ($values['enrollment_year'] < 1990 || $values['enrollment_year'] > (int) date('Y') + 1)) {
            $errors['enrollment_year'] = 'Enter a four-digit year.';
        }

        if ($values['enrollment'] !== null && $values['enrollment_year'] === null) {
            $errors['enrollment_year'] = 'Say which IPEDS year the enrollment figure is from. Every figure on the site shows its year.';
        }

        if ($values['slug'] === '' && $values['name'] !== '') {
            $values['slug'] = \Keel\App\Support\Format::slug($values['name']);
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $values['slug'])) {
            $errors['slug'] = 'Use lowercase letters, numbers and single hyphens.';
        } elseif (!isset($errors['state']) && School::slugTaken($values['state'], $values['slug'], $exceptId)) {
            $errors['slug'] = 'Another school in this state already uses this URL.';
        }

        $values['city'] = $values['city'] === '' ? null : $values['city'];
        $values['control'] = $values['control'] === '' ? null : $values['control'];

        return [$values, $errors];
    }
}
