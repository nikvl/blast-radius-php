<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model;

class ProjectModel
{
    /** @var array<string, ClassModel> keyed by FQCN */
    public array $classes = [];

    public function addClass(ClassModel $class): void
    {
        $this->classes[$class->fullyQualifiedName] = $class;
    }

    public function findByFqcn(string $fqcn): ?ClassModel
    {
        return $this->classes[$fqcn] ?? null;
    }

    public function findByShortName(string $name): ?ClassModel
    {
        foreach ($this->classes as $class) {
            if ($class->name === $name) {
                return $class;
            }
        }
        return null;
    }
}
