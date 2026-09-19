<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model;

use PhpUmlGenerator\Model\Diff\ChangeType;

class ClassModel
{
    public string $id = '';
    public string $name = '';
    public string $fullyQualifiedName = '';
    public string $namespace = '';
    public string $type = 'class'; // class|interface|trait|enum
    public bool $isAbstract = false;
    public bool $isFinal = false;
    public string $filePath = '';
    public int $startLine = 0;
    public int $endLine = 0;
    public string $fullCode = '';
    public string $contentHash = '';
    public ChangeType $changeType = ChangeType::UNCHANGED;

    /** @var MethodModel[] */
    public array $methods = [];
    /** @var PropertyModel[] */
    public array $properties = [];
    /** @var RelationModel[] */
    public array $relations = [];
}
