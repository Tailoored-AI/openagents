<?php

namespace App\Exceptions;

use App\Models\KnowledgePage;
use RuntimeException;

/**
 * Thrown when a save is based on an older version of the page than the one stored.
 */
class KnowledgePageConflictException extends RuntimeException
{
    public function __construct(public readonly KnowledgePage $page)
    {
        parent::__construct("Knowledge page [{$page->id}] is at version {$page->version}.");
    }
}
