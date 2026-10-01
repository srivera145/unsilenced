<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Models\ImportRun;
use Keel\Core\Request;

/**
 * Read-only history of import:schools and import:clery runs. Imports are
 * started from the command line (they read files on the server), never from
 * the browser.
 */
class ImportRunController extends AdminController
{
    public function index(Request $request): void
    {
        $this->view('admin.imports.index', ['title' => 'Imports', 'runs' => ImportRun::recent(200)]);
    }

    public function show(Request $request, string $id): void
    {
        $run = ImportRun::find((int) $id) ?? $this->notFound();

        $this->view('admin.imports.show', [
            'title' => 'Import run #' . (int) $run['id'],
            'run' => $run,
            'errors' => ImportRun::errors($run),
            'columns' => ImportRun::columnsFound($run),
        ]);
    }
}
