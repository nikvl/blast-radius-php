<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model\Diff;

class ImpactedElement
{
    public function __construct(
        public readonly string $elementId,
        public readonly string $reason,
    ) {}
}
