<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Analysis;

use PhpUmlGenerator\Model\Diff\ChangeType;
use PhpUmlGenerator\Model\Diff\ImpactedElement;
use PhpUmlGenerator\Model\ProjectModel;

class ImpactAnalyzer
{
    /** @return ImpactedElement[] */
    public function findImpacted(ProjectModel $model): array
    {
        // Build set of changed FQCNs and their short names for loose matching
        $changedFqcns = [];
        foreach ($model->classes as $class) {
            if ($class->changeType !== ChangeType::UNCHANGED) {
                $changedFqcns[$class->fullyQualifiedName] = true;
                $changedFqcns[$class->name] = true; // short name fallback
            }
        }

        if (empty($changedFqcns)) {
            return [];
        }

        // BFS: transitively find all dependents
        // ponytail: O(n²) per BFS level; fine for typical project sizes
        $impacted = [];
        $visitedIds = [];
        $queue = array_keys($changedFqcns);

        while (!empty($queue)) {
            $targetName = array_shift($queue);

            foreach ($model->classes as $class) {
                if (isset($visitedIds[$class->id])) {
                    continue;
                }
                foreach ($class->relations as $relation) {
                    $toFull = $class->namespace ? $class->namespace . '\\' . $relation->toName : $relation->toName;
                    if (isset($changedFqcns[$relation->toName]) || isset($changedFqcns[$toFull])) {
                        $impacted[] = new ImpactedElement($class->id, $relation->toName);
                        $visitedIds[$class->id] = true;
                        // Add this class's FQCN to the queue for transitive search
                        $queue[] = $class->fullyQualifiedName;
                        $queue[] = $class->name;
                        $changedFqcns[$class->fullyQualifiedName] = true;
                        $changedFqcns[$class->name] = true;
                        break;
                    }
                }
            }
        }

        return $impacted;
    }
}
