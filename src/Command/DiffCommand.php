<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Command;

use PhpUmlGenerator\Analysis\BaselineAnalyzer;
use PhpUmlGenerator\Analysis\ImpactAnalyzer;
use PhpUmlGenerator\Cache\BaselineCache;
use PhpUmlGenerator\Diff\DiffMapper;
use PhpUmlGenerator\Discovery\FileDiscovery;
use PhpUmlGenerator\Export\CodeIndexExporter;
use PhpUmlGenerator\Export\GraphJsonExporter;
use PhpUmlGenerator\Git\GitDiffExtractor;
use PhpUmlGenerator\Model\Diff\ChangeType;
use PhpUmlGenerator\Parsing\PhpFileParser;
use PhpUmlGenerator\Viewer\HtmlViewerBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'diff', description: 'Diff working tree against a git ref and highlight changes on the UML map')]
class DiffCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('against', null, InputOption::VALUE_REQUIRED, 'Compare against: HEAD, staged, or <commit>/<branch>', 'HEAD')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Source directory', 'src')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory', '.uml-cache')
            ->addOption('fail-threshold', null, InputOption::VALUE_OPTIONAL, 'Exit 1 if impacted module count exceeds this', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $against = (string) $input->getOption('against');
        $path = (string) $input->getOption('path');
        $outputDir = (string) $input->getOption('output');
        $threshold = $input->getOption('fail-threshold') !== null ? (int) $input->getOption('fail-threshold') : null;
        $cwd = getcwd();

        $cache = new BaselineCache($outputDir);

        // Auto-build baseline if missing
        if (!$cache->exists()) {
            $output->writeln('<comment>No baseline found — building now…</comment>');
            if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
            $model = (new BaselineAnalyzer(new FileDiscovery(), new PhpFileParser()))->analyze($path, $output);
            $cache->save($model);
        }

        $baseline = $cache->load();
        if ($baseline === null) {
            $output->writeln('<error>Failed to load baseline cache.</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Extracting diff against <info>%s</info>…', $against));
        $extractor = new GitDiffExtractor($cwd);
        $fileDiff = $extractor->extractDiff($against);

        if (empty($fileDiff)) {
            $output->writeln('<comment>No changes detected.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Changed files: <info>%d</info>', count($fileDiff)));

        // Build current model: start from baseline, re-parse changed files
        $currentModel = $cache->load(); // fresh copy
        $parser = new PhpFileParser();

        foreach (array_keys($fileDiff) as $file) {
            if (!file_exists($file) || !str_ends_with($file, '.php')) {
                continue;
            }
            try {
                foreach ($parser->parse($file) as $class) {
                    $currentModel->addClass($class);
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<comment>Skip %s: %s</comment>', basename($file), $e->getMessage()));
            }
        }

        (new DiffMapper())->applyDiff($currentModel, $fileDiff, $baseline);

        $impacted = (new ImpactAnalyzer())->findImpacted($currentModel);

        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

        $graph = (new GraphJsonExporter())->export($currentModel, $impacted);
        $index = (new CodeIndexExporter())->export($currentModel, $baseline);
        (new HtmlViewerBuilder())->build($outputDir, $graph, $index);

        $changedCount = count(array_filter(
            $currentModel->classes,
            fn($c) => $c->changeType !== ChangeType::UNCHANGED
        ));

        $output->writeln(sprintf(
            '<info>%d</info> classes changed · <info>%d</info> impacted · viewer: <comment>%s/index.html</comment>',
            $changedCount,
            count($impacted),
            $outputDir
        ));

        if ($threshold !== null && count($impacted) > $threshold) {
            $output->writeln(sprintf('<error>Impact threshold exceeded: %d > %d</error>', count($impacted), $threshold));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
