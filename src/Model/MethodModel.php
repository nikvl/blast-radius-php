<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model;

use PhpUmlGenerator\Model\Diff\ChangeType;

class MethodModel
{
    public string $id = '';
    public string $name = '';
    public string $visibility = 'public';
    public ?string $returnType = null;
    public bool $isStatic = false;
    public bool $isAbstract = false;
    /** @var string[] */
    public array $parameters = [];
    public int $startLine = 0;
    public int $endLine = 0;
    public string $fullCode = '';
    public string $contentHash = '';
    public ChangeType $changeType = ChangeType::UNCHANGED;
}
