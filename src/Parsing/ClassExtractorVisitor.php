<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Parsing;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use PhpUmlGenerator\Model\ClassModel;
use PhpUmlGenerator\Model\MethodModel;
use PhpUmlGenerator\Model\PropertyModel;
use PhpUmlGenerator\Model\RelationModel;

class ClassExtractorVisitor extends NodeVisitorAbstract
{
    private const SCALAR_TYPES = [
        'int', 'string', 'bool', 'float', 'null', 'void', 'never', 'mixed',
        'array', 'object', 'callable', 'iterable', 'self', 'static', 'parent',
        'false', 'true', 'resource',
    ];

    private string $currentNamespace = '';
    private ?ClassModel $currentClass = null;

    /** @var ClassModel[] */
    public array $classes = [];

    /** @param string[] $fileLines */
    public function __construct(
        private readonly string $filePath,
        private readonly array $fileLines,
    ) {}

    public function enterNode(Node $node): int|Node|null
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = $node->name?->toString() ?? '';
            return null;
        }

        if (
            $node instanceof Stmt\Class_
            || $node instanceof Stmt\Interface_
            || $node instanceof Stmt\Trait_
            || $node instanceof Stmt\Enum_
        ) {
            $this->currentClass = $this->buildClassModel($node);
            return null;
        }

        if ($this->currentClass !== null) {
            if ($node instanceof Stmt\ClassMethod) {
                $this->currentClass->methods[] = $this->buildMethodModel($node);
            } elseif ($node instanceof Stmt\Property) {
                foreach ($node->props as $prop) {
                    $this->currentClass->properties[] = $this->buildPropertyModel($node, $prop);
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): int|Node|array|null
    {
        if (
            $node instanceof Stmt\Class_
            || $node instanceof Stmt\Interface_
            || $node instanceof Stmt\Trait_
            || $node instanceof Stmt\Enum_
        ) {
            if ($this->currentClass !== null) {
                $this->classes[] = $this->currentClass;
                $this->currentClass = null;
            }
        }
        return null;
    }

    private function buildClassModel(
        Stmt\Class_|Stmt\Interface_|Stmt\Trait_|Stmt\Enum_ $node
    ): ClassModel {
        $model = new ClassModel();
        $model->name = $node->name?->name ?? 'anonymous';
        $model->namespace = $this->currentNamespace;
        $model->fullyQualifiedName = $this->currentNamespace
            ? $this->currentNamespace . '\\' . $model->name
            : $model->name;
        $model->id = md5($model->fullyQualifiedName);
        $model->filePath = $this->filePath;
        $model->startLine = $node->getStartLine();
        $model->endLine = $node->getEndLine();
        $model->fullCode = $this->extractLines($model->startLine, $model->endLine);
        $model->contentHash = md5($model->fullCode);

        $model->type = match (true) {
            $node instanceof Stmt\Interface_ => 'interface',
            $node instanceof Stmt\Trait_ => 'trait',
            $node instanceof Stmt\Enum_ => 'enum',
            default => 'class',
        };

        $model->isAbstract = $node instanceof Stmt\Class_ && $node->isAbstract();
        $model->isFinal = $node instanceof Stmt\Class_ && $node->isFinal();

        if ($node instanceof Stmt\Class_) {
            if ($node->extends !== null) {
                $model->relations[] = new RelationModel($model->id, $node->extends->toString(), 'extends');
            }
            foreach ($node->implements as $iface) {
                $model->relations[] = new RelationModel($model->id, $iface->toString(), 'implements');
            }
        }

        if ($node instanceof Stmt\Interface_) {
            foreach ($node->extends as $parent) {
                $model->relations[] = new RelationModel($model->id, $parent->toString(), 'extends');
            }
        }

        if ($node instanceof Stmt\Enum_) {
            foreach ($node->implements as $iface) {
                $model->relations[] = new RelationModel($model->id, $iface->toString(), 'implements');
            }
        }

        // Property type dependencies (composition/aggregation)
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\Property && $stmt->type !== null) {
                foreach ($this->extractClassNames($stmt->type) as $name) {
                    $model->relations[] = new RelationModel($model->id, $name, 'uses');
                }
            }
        }

        // Method parameter and return type dependencies
        foreach ($node->stmts as $stmt) {
            if (!($stmt instanceof Stmt\ClassMethod)) {
                continue;
            }
            foreach ($stmt->params as $param) {
                if ($param->type !== null) {
                    foreach ($this->extractClassNames($param->type) as $name) {
                        $model->relations[] = new RelationModel($model->id, $name, 'uses');
                    }
                }
            }
            if ($stmt->returnType !== null) {
                foreach ($this->extractClassNames($stmt->returnType) as $name) {
                    $model->relations[] = new RelationModel($model->id, $name, 'uses');
                }
            }
        }

        return $model;
    }

    private function buildMethodModel(Stmt\ClassMethod $node): MethodModel
    {
        $model = new MethodModel();
        $model->name = $node->name->name;
        $model->isStatic = $node->isStatic();
        $model->isAbstract = $node->isAbstract();
        $model->visibility = $node->isPublic() ? 'public' : ($node->isProtected() ? 'protected' : 'private');
        $model->returnType = $node->returnType !== null ? $this->typeToString($node->returnType) : null;
        $model->startLine = $node->getStartLine();
        $model->endLine = $node->getEndLine();
        $model->fullCode = $this->extractLines($model->startLine, $model->endLine);
        $model->contentHash = md5($model->fullCode);

        foreach ($node->params as $param) {
            $typeStr = $param->type !== null ? $this->typeToString($param->type) . ' ' : '';
            $varName = $param->var instanceof Node\Expr\Variable ? (string) $param->var->name : '?';
            $model->parameters[] = $typeStr . '$' . $varName;
        }

        $className = $this->currentClass?->fullyQualifiedName ?? '';
        $model->id = md5($className . '::' . $model->name);

        return $model;
    }

    private function buildPropertyModel(Stmt\Property $node, Stmt\PropertyProperty $prop): PropertyModel
    {
        $model = new PropertyModel();
        $model->name = $prop->name->name;
        $model->visibility = $node->isPublic() ? 'public' : ($node->isProtected() ? 'protected' : 'private');
        $model->isStatic = $node->isStatic();
        $model->type = $node->type !== null ? $this->typeToString($node->type) : null;
        $model->startLine = $node->getStartLine();
        $model->endLine = $node->getEndLine();
        $model->fullCode = $this->extractLines($model->startLine, $model->endLine);
        $model->contentHash = md5($model->fullCode);

        $className = $this->currentClass?->fullyQualifiedName ?? '';
        $model->id = md5($className . '::$' . $model->name);

        return $model;
    }

    /** @return string[] class names (non-scalar, non-vendor) from a type node */
    private function extractClassNames(Node $type): array
    {
        $names = [];

        if ($type instanceof Node\Name) {
            $name = $type->toString();
            if (!in_array(strtolower($name), self::SCALAR_TYPES, true)) {
                $names[] = $name;
            }
        } elseif ($type instanceof Node\NullableType) {
            $names = array_merge($names, $this->extractClassNames($type->type));
        } elseif ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            foreach ($type->types as $t) {
                $names = array_merge($names, $this->extractClassNames($t));
            }
        }

        return $names;
    }

    private function typeToString(Node $type): string
    {
        return match (true) {
            $type instanceof Node\Name => $type->toString(),
            $type instanceof Node\Identifier => $type->name,
            $type instanceof Node\NullableType => '?' . $this->typeToString($type->type),
            $type instanceof Node\UnionType => implode('|', array_map($this->typeToString(...), $type->types)),
            $type instanceof Node\IntersectionType => implode('&', array_map($this->typeToString(...), $type->types)),
            default => (string) $type,
        };
    }

    private function extractLines(int $start, int $end): string
    {
        return implode("\n", array_slice($this->fileLines, $start - 1, $end - $start + 1));
    }
}
