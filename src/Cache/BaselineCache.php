<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Cache;

use PhpUmlGenerator\Model\ClassModel;
use PhpUmlGenerator\Model\Diff\ChangeType;
use PhpUmlGenerator\Model\MethodModel;
use PhpUmlGenerator\Model\ProjectModel;
use PhpUmlGenerator\Model\PropertyModel;
use PhpUmlGenerator\Model\RelationModel;

class BaselineCache
{
    private string $cacheFile;

    public function __construct(string $outputDir)
    {
        $this->cacheFile = rtrim($outputDir, '/') . '/baseline-cache.json';
    }

    public function save(ProjectModel $model): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [];
        foreach ($model->classes as $fqcn => $class) {
            $data[$fqcn] = $this->serialize($class);
        }
        file_put_contents($this->cacheFile, json_encode($data));
    }

    public function load(): ?ProjectModel
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->cacheFile), true);
        if (!is_array($data)) {
            return null;
        }

        $model = new ProjectModel();
        foreach ($data as $classData) {
            $model->addClass($this->deserialize($classData));
        }
        return $model;
    }

    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }

    private function serialize(ClassModel $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'fqcn' => $c->fullyQualifiedName,
            'namespace' => $c->namespace,
            'type' => $c->type,
            'isAbstract' => $c->isAbstract,
            'isFinal' => $c->isFinal,
            'filePath' => $c->filePath,
            'startLine' => $c->startLine,
            'endLine' => $c->endLine,
            'fullCode' => $c->fullCode,
            'contentHash' => $c->contentHash,
            'methods' => array_map(fn($m) => [
                'id' => $m->id, 'name' => $m->name, 'visibility' => $m->visibility,
                'returnType' => $m->returnType, 'isStatic' => $m->isStatic, 'isAbstract' => $m->isAbstract,
                'parameters' => $m->parameters, 'startLine' => $m->startLine, 'endLine' => $m->endLine,
                'fullCode' => $m->fullCode, 'contentHash' => $m->contentHash,
            ], $c->methods),
            'properties' => array_map(fn($p) => [
                'id' => $p->id, 'name' => $p->name, 'visibility' => $p->visibility,
                'type' => $p->type, 'isStatic' => $p->isStatic,
                'startLine' => $p->startLine, 'endLine' => $p->endLine,
                'fullCode' => $p->fullCode, 'contentHash' => $p->contentHash,
            ], $c->properties),
            'relations' => array_map(fn($r) => [
                'fromId' => $r->fromId, 'toName' => $r->toName, 'type' => $r->type,
            ], $c->relations),
        ];
    }

    private function deserialize(array $d): ClassModel
    {
        $c = new ClassModel();
        $c->id = $d['id'];
        $c->name = $d['name'];
        $c->fullyQualifiedName = $d['fqcn'];
        $c->namespace = $d['namespace'];
        $c->type = $d['type'];
        $c->isAbstract = $d['isAbstract'];
        $c->isFinal = $d['isFinal'];
        $c->filePath = $d['filePath'];
        $c->startLine = $d['startLine'];
        $c->endLine = $d['endLine'];
        $c->fullCode = $d['fullCode'];
        $c->contentHash = $d['contentHash'];
        $c->changeType = ChangeType::UNCHANGED;

        foreach ($d['methods'] ?? [] as $m) {
            $method = new MethodModel();
            $method->id = $m['id']; $method->name = $m['name'];
            $method->visibility = $m['visibility']; $method->returnType = $m['returnType'];
            $method->isStatic = $m['isStatic']; $method->isAbstract = $m['isAbstract'];
            $method->parameters = $m['parameters'];
            $method->startLine = $m['startLine']; $method->endLine = $m['endLine'];
            $method->fullCode = $m['fullCode']; $method->contentHash = $m['contentHash'];
            $method->changeType = ChangeType::UNCHANGED;
            $c->methods[] = $method;
        }

        foreach ($d['properties'] ?? [] as $p) {
            $prop = new PropertyModel();
            $prop->id = $p['id']; $prop->name = $p['name'];
            $prop->visibility = $p['visibility']; $prop->type = $p['type'];
            $prop->isStatic = $p['isStatic'];
            $prop->startLine = $p['startLine']; $prop->endLine = $p['endLine'];
            $prop->fullCode = $p['fullCode']; $prop->contentHash = $p['contentHash'];
            $prop->changeType = ChangeType::UNCHANGED;
            $c->properties[] = $prop;
        }

        foreach ($d['relations'] ?? [] as $r) {
            $c->relations[] = new RelationModel($r['fromId'], $r['toName'], $r['type']);
        }

        return $c;
    }
}
