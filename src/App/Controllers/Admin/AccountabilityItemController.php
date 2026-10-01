<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Models\AccountabilityItem;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\App\Support\NameDetector;
use Keel\Core\Activity;
use Keel\Core\Request;

/**
 * Accountability records. The summary is checked for anything that looks like
 * a person's full name; a flagged summary is not saved until the admin ticks
 * the confirmation, and who confirmed it and when is stored on the row.
 */
class AccountabilityItemController extends AdminController
{
    public function index(Request $request): void
    {
        $schoolId = $this->intOrNull($request, 'school_id');
        $school = $schoolId !== null ? School::find($schoolId) : null;

        $this->view('admin.accountability.index', [
            'title' => 'Accountability records',
            'school' => $school,
            'items' => AccountabilityItem::adminList($school !== null ? (int) $school['id'] : null),
        ]);
    }

    public function create(Request $request): void
    {
        $school = ($id = $this->intOrNull($request, 'school_id')) !== null ? School::find($id) : null;

        $this->view('admin.accountability.form', [
            'title' => 'New accountability record',
            'item' => null,
            'school' => $school,
            'values' => [
                'unitid' => $school['unitid'] ?? '',
                'is_published' => 1,
                'type' => '',
                'status' => '',
            ],
            'errors' => [],
            'flagged' => [],
        ]);
    }

    public function store(Request $request): void
    {
        [$values, $errors, $school, $flagged] = $this->validated($request);

        if ($errors !== []) {
            $this->invalid('admin.accountability.form', [
                'title' => 'New accountability record',
                'item' => null,
                'school' => $school,
                'values' => $values,
                'errors' => $errors,
                'flagged' => $flagged,
            ]);

            return;
        }

        $id = AccountabilityItem::create($values + ['created_by' => $this->adminId(), 'updated_by' => $this->adminId()]);
        Activity::log('accountability_item.created', 'AccountabilityItem', $id, ['name_check_confirmed' => $flagged !== []]);
        $this->redirect('/admin/accountability/' . $id . '/edit?status=created');
    }

    public function edit(Request $request, string $id): void
    {
        $item = AccountabilityItem::find((int) $id) ?? $this->notFound();
        $school = School::find((int) $item['school_id']);

        $this->view('admin.accountability.form', [
            'title' => 'Edit accountability record',
            'item' => $item,
            'school' => $school,
            'values' => $item + ['unitid' => $school['unitid'] ?? ''],
            'errors' => [],
            'flagged' => [],
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $item = AccountabilityItem::find((int) $id) ?? $this->notFound();
        [$values, $errors, $school, $flagged] = $this->validated($request);

        if ($errors !== []) {
            $this->invalid('admin.accountability.form', [
                'title' => 'Edit accountability record',
                'item' => $item,
                'school' => $school ?? School::find((int) $item['school_id']),
                'values' => $values,
                'errors' => $errors,
                'flagged' => $flagged,
            ]);

            return;
        }

        AccountabilityItem::update((int) $item['id'], $values + ['updated_by' => $this->adminId()]);
        Activity::log('accountability_item.updated', 'AccountabilityItem', (int) $item['id'], ['name_check_confirmed' => $flagged !== []]);
        $this->redirect('/admin/accountability/' . (int) $item['id'] . '/edit?status=saved');
    }

    public function destroy(Request $request, string $id): void
    {
        $item = AccountabilityItem::find((int) $id) ?? $this->notFound();
        AccountabilityItem::delete((int) $item['id']);
        Activity::log('accountability_item.deleted', 'AccountabilityItem', (int) $item['id']);
        $this->redirect('/admin/accountability?school_id=' . (int) $item['school_id'] . '&status=deleted');
    }

    /** @return array{0: array, 1: array<string, string>, 2: ?array, 3: list<string>} */
    private function validated(Request $request): array
    {
        $types = (array) Config::get('accountability.types', []);
        $statuses = (array) Config::get('accountability.statuses', []);
        $maxSummary = (int) Config::get('accountability.summary_max_length', 1000);

        $unitid = $this->intOrNull($request, 'unitid');
        $school = $unitid !== null ? School::findByUnitid($unitid) : null;
        $rawSummary = str_replace("\r\n", "\n", trim((string) $request->input('summary', '')));

        $values = [
            'unitid' => $unitid,
            'school_id' => $school['id'] ?? null,
            'type' => $this->text($request, 'type', 40),
            'item_date' => $this->text($request, 'item_date', 10),
            'summary' => mb_substr($rawSummary, 0, $maxSummary),
            'status' => $this->text($request, 'status', 30),
            'source_name' => $this->text($request, 'source_name', 255),
            'source_url' => $this->text($request, 'source_url', 2048),
            'is_published' => $this->checked($request, 'is_published') ? 1 : 0,
        ];
        $errors = [];

        if ($school === null) {
            $errors['unitid'] = 'Enter the UNITID of a school that is in the database.';
        }

        if (!array_key_exists($values['type'], $types)) {
            $errors['type'] = 'Choose a type.';
        }

        if (!self::validDate($values['item_date'])) {
            $errors['item_date'] = 'Enter a date as YYYY-MM-DD.';
        }

        if ($values['summary'] === '') {
            $errors['summary'] = 'Write a short summary in your own words.';
        } elseif (mb_strlen($rawSummary) > $maxSummary) {
            $errors['summary'] = "Keep the summary to {$maxSummary} characters.";
        }

        if (!array_key_exists($values['status'], $statuses)) {
            $errors['status'] = 'Choose a status.';
        }

        if ($values['source_name'] === '') {
            $errors['source_name'] = 'Name the source, e.g. the publication or agency.';
        }

        if (!self::validHttpUrl($values['source_url'])) {
            $errors['source_url'] = 'Enter the full source address, starting with https://.';
        }

        // The school's own name is not a person's name.
        $ignore = $school !== null ? (preg_split('/[^\p{L}\']+/u', (string) $school['name']) ?: []) : [];
        $flagged = NameDetector::find($values['summary'], $ignore);

        if ($flagged !== []) {
            if ($this->checked($request, 'confirm_no_names')) {
                $values['name_check_confirmed_by'] = $this->adminId();
                $values['name_check_confirmed_at'] = date('Y-m-d H:i:s');
            } else {
                $errors['confirm_no_names'] = 'This summary contains words that look like a person\'s full name. Unsilenced never names individuals. Remove any names, or confirm these are not names of people.';
            }
        } else {
            $values['name_check_confirmed_by'] = null;
            $values['name_check_confirmed_at'] = null;
        }

        // 'unitid' stays in $values for re-rendering the form; the model only
        // writes its FILLABLE columns.
        return [$values, $errors, $school, $flagged];
    }
}
