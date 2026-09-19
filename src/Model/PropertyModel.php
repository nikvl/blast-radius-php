<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model;

use PhpUmlGenerator\Model\Diff\ChangeType;

class PropertyModel
{
    public string $id = '';
    public string $name = '';
    public string $visibility = 'public';
    public ?string $type = null;
    public bool $isStatic = false;
    public int $startLine = 0;
    public int $endLine = 0;
    public string $fullCode = '';
    public string $contentHash = '';
    public ChangeType $changeType = ChangeType::UNCHANGED;
}
