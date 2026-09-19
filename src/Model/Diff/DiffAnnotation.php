<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model\Diff;

class DiffAnnotation
{
    public function __construct(
        public readonly string $elementId,
        public readonly ChangeType $changeType,
        public readonly string $currentCode,
        public readonly string $previousCode = '',
        /** @var array<array{type: string, text: string}> */
        public readonly array $diffLines = [],
    ) {}
}
