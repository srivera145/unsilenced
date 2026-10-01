<?php

namespace Keel\App\Services\Survivor;

/**
 * A file that cannot go in the vault. The message is shown to the person
 * uploading it, so it is plain language and never repeats the file's name or
 * contents (those must not reach a log either).
 */
final class EvidenceRejected extends \RuntimeException
{
}
