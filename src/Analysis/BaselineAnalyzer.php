<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Analysis;

use PhpUmlGenerator\Discovery\FileDiscovery;
use PhpUmlGenerator\Model\ProjectModel;
use PhpUmlGenerator\Parsing\PhpFileParser;
use Symfony\Component\Console\Output\OutputInterface;

class BaselineAnalyzer
{
    public function __construct(
        private readonly FileDiscovery $discovery,
        private readonly PhpFileParser $parser,
    ) {}

    public function analyze(string $path, OutputInterface $output, array $excludes = []): ProjectModel
    {
        $model = new ProjectModel();

        if (!is_dir($path)) {
            $output->writeln(sprintf('<error>Path not found: %s</error>', $path));
            return $model;
        }

        $files = $this->discovery->findPhpFiles($path, $excludes);
        $output->writeln(sprintf('Found <info>%d</info> PHP files in %s', count($files), $path));

        foreach ($files as $file) {
            try {
                foreach ($this->parser->parse($file) as $class) {
                    $model->addClass($class);
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<comment>Skip %s: %s</comment>', basename($file), $e->getMessage()));
            }
        }

        $output->writeln(sprintf('Extracted <info>%d</info> classes/interfaces/traits/enums', count($model->classes)));

        return $model;
    }
}
