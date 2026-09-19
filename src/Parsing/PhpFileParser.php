<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Parsing;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpUmlGenerator\Model\ClassModel;

class PhpFileParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /** @return ClassModel[] */
    public function parse(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return [];
        }

        $errorHandler = new Collecting();
        $ast = $this->parser->parse($code, $errorHandler);

        if ($ast === null) {
            return [];
        }

        $lines = explode("\n", $code);
        $visitor = new ClassExtractorVisitor($filePath, $lines);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->classes;
    }
}
