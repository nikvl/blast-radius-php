<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Diff;

use PhpUmlGenerator\Model\ClassModel;
use PhpUmlGenerator\Model\Diff\ChangeType;
use PhpUmlGenerator\Model\ProjectModel;

class DiffMapper
{
    /**
     * Annotates $currentModel classes/methods/properties with ChangeType.
     * Requires $baseline to distinguish ADDED vs MODIFIED.
     *
     * @param array<string, array{added: int[], removed: int[]}> $fileDiff
     */
    public function applyDiff(ProjectModel $currentModel, array $fileDiff, ProjectModel $baseline): void
    {
        $changedPaths = array_keys($fileDiff);

        foreach ($currentModel->classes as $fqcn => $class) {
            if (!in_array($class->filePath, $changedPaths, true)) {
                continue;
            }

            $diff = $fileDiff[$class->filePath];
            $baseClass = $baseline->findByFqcn($fqcn);

            if ($baseClass === null) {
                $this->markEntireClass($class, ChangeType::ADDED);
                continue;
            }

            $this->applyToClass($class, $diff, $baseClass);
        }

        // Mark REMOVED: classes in baseline whose file was changed but FQCN no longer exists
        foreach ($baseline->classes as $fqcn => $baseClass) {
            if (!isset($currentModel->classes[$fqcn]) && in_array($baseClass->filePath, $changedPaths, true)) {
                $removed = $this->cloneForRemoval($baseClass);
                $currentModel->addClass($removed);
            }
        }
    }

    private function applyToClass(ClassModel $class, array $diff, ClassModel $baseClass): void
    {
        $classHasChange = false;

        foreach ($class->methods as $method) {
            $baseMethod = $this->findMember($baseClass->methods, $method->name);
            $ct = $this->changeTypeForRange($method->startLine, $method->endLine, $diff, $baseMethod !== null);
            $method->changeType = $ct;
            if ($ct !== ChangeType::UNCHANGED) {
                $classHasChange = true;
            }
        }

        // Detect removed methods (in baseline but not in current)
        foreach ($baseClass->methods as $baseMethod) {
            $exists = $this->findMember($class->methods, $baseMethod->name) !== null;
            if (!$exists) {
                $removed = clone $baseMethod;
                $removed->changeType = ChangeType::REMOVED;
                $class->methods[] = $removed;
                $classHasChange = true;
            }
        }

        foreach ($class->properties as $prop) {
            $baseProp = $this->findMember($baseClass->properties, $prop->name);
            $ct = $this->changeTypeForRange($prop->startLine, $prop->endLine, $diff, $baseProp !== null);
            $prop->changeType = $ct;
            if ($ct !== ChangeType::UNCHANGED) {
                $classHasChange = true;
            }
        }

        foreach ($baseClass->properties as $baseProp) {
            $exists = $this->findMember($class->properties, $baseProp->name) !== null;
            if (!$exists) {
                $removed = clone $baseProp;
                $removed->changeType = ChangeType::REMOVED;
                $class->properties[] = $removed;
                $classHasChange = true;
            }
        }

        if ($classHasChange) {
            $class->changeType = ChangeType::MODIFIED;
        }
    }

    private function changeTypeForRange(int $start, int $end, array $diff, bool $existsInBaseline): ChangeType
    {
        $addedHit = $this->linesOverlap($diff['added'], $start, $end);
        $removedHit = $this->linesOverlap($diff['removed'], $start, $end);

        if (!$existsInBaseline && $addedHit) {
            return ChangeType::ADDED;
        }
        if ($addedHit || $removedHit) {
            return ChangeType::MODIFIED;
        }
        return ChangeType::UNCHANGED;
    }

    /** @param array<object{name: string}> $members */
    private function findMember(array $members, string $name): mixed
    {
        foreach ($members as $m) {
            if ($m->name === $name) {
                return $m;
            }
        }
        return null;
    }

    private function linesOverlap(array $lines, int $start, int $end): bool
    {
        foreach ($lines as $line) {
            if ($line >= $start && $line <= $end) {
                return true;
            }
        }
        return false;
    }

    private function markEntireClass(ClassModel $class, ChangeType $type): void
    {
        $class->changeType = $type;
        foreach ($class->methods as $m) {
            $m->changeType = $type;
        }
        foreach ($class->properties as $p) {
            $p->changeType = $type;
        }
    }

    private function cloneForRemoval(ClassModel $base): ClassModel
    {
        $c = clone $base;
        $c->changeType = ChangeType::REMOVED;
        return $c;
    }
}
