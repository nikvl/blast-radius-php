<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Export;

use PhpUmlGenerator\Diff\LineDiff;
use PhpUmlGenerator\Model\ClassModel;
use PhpUmlGenerator\Model\Diff\ChangeType;
use PhpUmlGenerator\Model\MethodModel;
use PhpUmlGenerator\Model\ProjectModel;
use PhpUmlGenerator\Model\PropertyModel;

class CodeIndexExporter
{
    /**
     * @return array<string, array{signature: string, changeType: string, fullCode: string, diffLines: array}>
     */
    public function export(ProjectModel $current, ?ProjectModel $baseline = null): array
    {
        $index = [];

        foreach ($current->classes as $class) {
            $baseClass = $baseline?->findByFqcn($class->fullyQualifiedName);

            $index[$class->id] = [
                'signature' => sprintf('«%s» %s', $class->type, $class->name),
                'changeType' => $class->changeType->value,
                'fullCode' => $class->fullCode,
                'language' => 'php',
                'diffLines' => $this->buildDiffLines($class, $baseClass),
            ];

            foreach ($class->methods as $method) {
                $baseMethod = $baseClass ? $this->findMember($baseClass->methods, $method->name) : null;
                $index[$method->id] = [
                    'signature' => $this->methodSignature($method),
                    'changeType' => $method->changeType->value,
                    'fullCode' => $method->fullCode,
                    'language' => 'php',
                    'diffLines' => $this->memberDiffLines($method, $baseMethod),
                ];
            }

            foreach ($class->properties as $prop) {
                $baseProp = $baseClass ? $this->findMember($baseClass->properties, $prop->name) : null;
                $index[$prop->id] = [
                    'signature' => $this->propSignature($prop),
                    'changeType' => $prop->changeType->value,
                    'fullCode' => $prop->fullCode,
                    'language' => 'php',
                    'diffLines' => $this->memberDiffLines($prop, $baseProp),
                ];
            }
        }

        return $index;
    }

    private function buildDiffLines(ClassModel $class, ?ClassModel $base): array
    {
        if ($class->changeType === ChangeType::UNCHANGED || $base === null) {
            return [];
        }
        if ($class->changeType === ChangeType::ADDED) {
            return [];
        }
        if ($class->changeType === ChangeType::REMOVED) {
            return array_map(fn($l) => ['type' => 'removed', 'text' => $l], explode("\n", $class->fullCode));
        }
        return LineDiff::compute($base->fullCode, $class->fullCode);
    }

    private function memberDiffLines(MethodModel|PropertyModel $member, MethodModel|PropertyModel|null $base): array
    {
        if ($member->changeType === ChangeType::UNCHANGED || $base === null) {
            return [];
        }
        if ($member->changeType === ChangeType::ADDED) {
            return [];
        }
        if ($member->changeType === ChangeType::REMOVED) {
            return array_map(fn($l) => ['type' => 'removed', 'text' => $l], explode("\n", $member->fullCode));
        }
        return LineDiff::compute($base->fullCode, $member->fullCode);
    }

    private function methodSignature(MethodModel $m): string
    {
        $sig = $m->visibility;
        if ($m->isStatic) $sig .= ' static';
        if ($m->isAbstract) $sig .= ' abstract';
        $sig .= ' function ' . $m->name . '(' . implode(', ', $m->parameters) . ')';
        if ($m->returnType) $sig .= ': ' . $m->returnType;
        return $sig;
    }

    private function propSignature(PropertyModel $p): string
    {
        $sig = $p->visibility;
        if ($p->isStatic) $sig .= ' static';
        if ($p->type) $sig .= ' ' . $p->type;
        return $sig . ' $' . $p->name;
    }

    private function findMember(array $members, string $name): mixed
    {
        foreach ($members as $m) {
            if ($m->name === $name) return $m;
        }
        return null;
    }
}
