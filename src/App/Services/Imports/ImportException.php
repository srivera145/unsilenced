<?php

namespace Keel\App\Services\Imports;

/**
 * A problem with the file itself: a missing column, an unknown location, a year
 * the file has no data for. Running again will fail the same way, so the queued
 * job records it on the import run and does not retry.
 */
class ImportException extends \RuntimeException
{
}
