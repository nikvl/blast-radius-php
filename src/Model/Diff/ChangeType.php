<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model\Diff;

enum ChangeType: string
{
    case ADDED = 'ADDED';
    case MODIFIED = 'MODIFIED';
    case REMOVED = 'REMOVED';
    case UNCHANGED = 'UNCHANGED';
}
