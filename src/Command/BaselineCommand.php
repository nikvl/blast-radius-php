<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Command;

use PhpUmlGenerator\Analysis\BaselineAnalyzer;
use PhpUmlGenerator\Cache\BaselineCache;
use PhpUmlGenerator\Discovery\FileDiscovery;
use PhpUmlGenerator\Export\CodeIndexExporter;
use PhpUmlGenerator\Export\GraphJsonExporter;
use PhpUmlGenerator\Parsing\PhpFileParser;
use PhpUmlGenerator\Viewer\HtmlViewerBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'baseline', description: 'Build full UML baseline, cache it, and generate the viewer')]
class BaselineCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Source directory to analyse', 'src')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory for artefacts', '.uml-cache')
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Paths to exclude (relative to --path)', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getOption('path');
        $outputDir = (string) $input->getOption('output');
        $excludes = (array) $input->getOption('exclude');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->suggestGitignore($outputDir, $output);

        $output->writeln('<info>Building baseline…</info>');

        $model = (new BaselineAnalyzer(new FileDiscovery(), new PhpFileParser()))
            ->analyze($path, $output, $excludes);

        $cache = new BaselineCache($outputDir);
        $cache->save($model);
        $output->writeln('Baseline cached.');

        $graph = (new GraphJsonExporter())->export($model);
        $index = (new CodeIndexExporter())->export($model);
        (new HtmlViewerBuilder())->build($outputDir, $graph, $index);

        $output->writeln(sprintf('<info>Done.</info> Open <comment>%s/index.html</comment>', $outputDir));

        return Command::SUCCESS;
    }

    private function suggestGitignore(string $outputDir, OutputInterface $output): void
    {
        $gitignore = getcwd() . '/.gitignore';
        $entry = '/' . ltrim($outputDir, '/');

        if (file_exists($gitignore) && str_contains((string) file_get_contents($gitignore), $outputDir)) {
            return;
        }

        $output->writeln(sprintf(
            '<comment>%s contains full source code — it should not be committed.</comment>',
            $outputDir
        ));

        if (!file_exists($gitignore)) {
            $output->writeln(sprintf('<comment>No .gitignore found. Create one and add "%s".</comment>', $entry));
            return;
        }

        $output->write(sprintf('Add "%s" to .gitignore? [Y/n] ', $entry));
        $answer = strtolower(trim((string) fgets(STDIN)));
        if ($answer === '' || $answer === 'y') {
            file_put_contents($gitignore, "\n{$entry}\n", FILE_APPEND);
            $output->writeln(sprintf('<info>Added "%s" to .gitignore.</info>', $entry));
        }
    }
}
