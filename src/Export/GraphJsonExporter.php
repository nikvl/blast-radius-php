<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Export;

use PhpUmlGenerator\Model\Diff\ImpactedElement;
use PhpUmlGenerator\Model\ProjectModel;

class GraphJsonExporter
{
    /**
     * @param ImpactedElement[] $impacted
     * @return array{nodes: array[], edges: array[]}
     */
    public function export(ProjectModel $model, array $impacted = []): array
    {
        $impactedIds = [];
        foreach ($impacted as $el) {
            $impactedIds[$el->elementId] = $el->reason;
        }

        $nodes = [];
        $edgeIds = [];
        $edges = [];

        // Build domain parent nodes
        $domains = [];
        foreach ($model->classes as $class) {
            $domain = $this->extractDomain($class->namespace);
            if ($domain !== null) {
                $domains[$domain] = true;
            }
        }

        foreach (array_keys($domains) as $domain) {
            $nodes[] = ['data' => [
                'id' => $this->domainId($domain),
                'label' => $this->domainLabel($domain),
                'isDomain' => true,
            ]];
        }

        foreach ($model->classes as $class) {
            $domain = $this->extractDomain($class->namespace);
            $nodeData = [
                'id' => $class->id,
                'label' => $class->name,
                'fqcn' => $class->fullyQualifiedName,
                'namespace' => $class->namespace,
                'type' => $class->type,
                'isAbstract' => $class->isAbstract,
                'isFinal' => $class->isFinal,
                'changeType' => $class->changeType->value,
                'isImpacted' => isset($impactedIds[$class->id]),
                'impactReason' => $impactedIds[$class->id] ?? null,
                'filePath' => $class->filePath,
                'methods' => array_map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'visibility' => $m->visibility,
                    'returnType' => $m->returnType,
                    'isStatic' => $m->isStatic,
                    'isAbstract' => $m->isAbstract,
                    'parameters' => $m->parameters,
                    'changeType' => $m->changeType->value,
                ], $class->methods),
                'properties' => array_map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'visibility' => $p->visibility,
                    'type' => $p->type,
                    'isStatic' => $p->isStatic,
                    'changeType' => $p->changeType->value,
                ], $class->properties),
            ];
            if ($domain !== null) {
                $nodeData['parent'] = $this->domainId($domain);
            }
            $nodes[] = ['data' => $nodeData];

            foreach ($class->relations as $relation) {
                $target = $model->findByFqcn($relation->toName)
                    ?? $model->findByShortName($relation->toName);
                if ($target === null) {
                    continue;
                }
                $edgeId = 'e-' . $class->id . '-' . $target->id . '-' . $relation->type;
                if (isset($edgeIds[$edgeId])) {
                    continue;
                }
                $edgeIds[$edgeId] = true;
                $edges[] = ['data' => [
                    'id' => $edgeId,
                    'source' => $class->id,
                    'target' => $target->id,
                    'type' => $relation->type,
                ]];
            }
        }

        // Aggregate domain-level edges from class edges
        $classById = [];
        foreach ($model->classes as $class) {
            $classById[$class->id] = $class;
        }

        $domainPairs = []; // "srcDomainId--tgtDomainId" => count
        foreach ($edges as $edge) {
            $src = $classById[$edge['data']['source']] ?? null;
            $tgt = $classById[$edge['data']['target']] ?? null;
            if ($src === null || $tgt === null) {
                continue;
            }
            $srcDomain = $this->extractDomain($src->namespace);
            $tgtDomain = $this->extractDomain($tgt->namespace);
            if ($srcDomain === null || $tgtDomain === null || $srcDomain === $tgtDomain) {
                continue;
            }
            $key = $this->domainId($srcDomain) . '--' . $this->domainId($tgtDomain);
            $domainPairs[$key] = ($domainPairs[$key] ?? 0) + 1;
        }

        foreach ($domainPairs as $key => $count) {
            [$srcId, $tgtId] = explode('--', $key, 2);
            $edges[] = ['data' => [
                'id' => 'de-' . $srcId . '-' . $tgtId,
                'source' => $srcId,
                'target' => $tgtId,
                'type' => 'domain',
                'count' => $count,
                'isDomainEdge' => true,
            ]];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function extractDomain(string $namespace): ?string
    {
        if ($namespace === '') {
            return null;
        }
        // App\Modules\Domain\... → App\Modules\Domain
        if (preg_match('/^([A-Za-z]+\\\\Modules\\\\[A-Za-z]+)/', $namespace, $m)) {
            return $m[1];
        }
        // First 2 namespace segments as fallback group
        $parts = explode('\\', trim($namespace, '\\'));
        if (count($parts) >= 2) {
            return implode('\\', array_slice($parts, 0, 2));
        }
        return count($parts) > 0 ? $parts[0] : null;
    }

    private function domainId(string $domain): string
    {
        return 'domain-' . substr(md5($domain), 0, 12);
    }

    private function domainLabel(string $domain): string
    {
        $parts = explode('\\', $domain);
        return end($parts);
    }
}
