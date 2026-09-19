<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Model;

class RelationModel
{
    public function __construct(
        public readonly string $fromId,
        public readonly string $toName,
        public readonly string $type, // 'extends', 'implements', 'uses'
    ) {}
}
