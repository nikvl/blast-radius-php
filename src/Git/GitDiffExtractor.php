<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Git;

use Symfony\Component\Process\Process;

class GitDiffExtractor
{
    public function __construct(private readonly string $workingDir) {}

    /**
     * @return array<string, array{added: int[], removed: int[]}>
     * Absolute file path → added/removed line numbers
     */
    public function extractDiff(string $against): array
    {
        $args = match ($against) {
            'staged' => ['git', 'diff', '--cached', '--unified=0'],
            'working-tree', 'HEAD' => ['git', 'diff', 'HEAD', '--unified=0'],
            default => ['git', 'diff', $against, '--unified=0'],
        };

        $proc = new Process($args, $this->workingDir);
        $proc->run();

        $result = $this->parseDiff($proc->getOutput());

        // Also handle untracked PHP files (git diff can't see them)
        foreach ($this->getUntrackedPhpFiles() as $relPath) {
            $absPath = $this->workingDir . '/' . $relPath;
            $lines = @file($absPath, FILE_IGNORE_NEW_LINES);
            if ($lines !== false) {
                $result[$absPath] = ['added' => range(1, count($lines)), 'removed' => []];
            }
        }

        return $result;
    }

    /** @return string[] relative paths of deleted files */
    public function getDeletedFiles(): array
    {
        $proc = new Process(['git', 'status', '--porcelain'], $this->workingDir);
        $proc->run();

        $deleted = [];
        foreach (explode("\n", $proc->getOutput()) as $line) {
            // D in index (staged delete) or working tree delete
            if (preg_match('/^[D ][D ] (.+\.php)$/', trim($line), $m)) {
                $deleted[] = $this->workingDir . '/' . trim($m[1]);
            }
        }
        return $deleted;
    }

    /** @return string[] relative paths of untracked PHP files */
    private function getUntrackedPhpFiles(): array
    {
        $proc = new Process(['git', 'status', '--porcelain'], $this->workingDir);
        $proc->run();

        $files = [];
        foreach (explode("\n", $proc->getOutput()) as $line) {
            if (str_starts_with($line, '?? ') && str_ends_with(trim($line), '.php')) {
                $files[] = trim(substr($line, 3));
            }
        }
        return $files;
    }

    /** @return array<string, array{added: int[], removed: int[]}> */
    private function parseDiff(string $raw): array
    {
        $result = [];
        $currentFile = null;
        $oldFile = null;
        $newLine = 0;
        $oldLine = 0;

        foreach (explode("\n", $raw) as $line) {
            if (str_starts_with($line, '--- a/')) {
                $oldFile = $this->workingDir . '/' . substr($line, 6);
                continue;
            }

            if (str_starts_with($line, '+++ b/')) {
                $currentFile = $this->workingDir . '/' . substr($line, 6);
                $result[$currentFile] ??= ['added' => [], 'removed' => []];
                continue;
            }

            // Deleted file: +++ /dev/null
            if ($line === '+++ /dev/null' && $oldFile !== null) {
                $currentFile = $oldFile;
                $result[$currentFile] ??= ['added' => [], 'removed' => []];
                continue;
            }

            if ($currentFile === null) {
                continue;
            }

            if (preg_match('/^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $m)) {
                $oldLine = (int) $m[1];
                $newLine = (int) $m[2];
                continue;
            }

            if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
                $result[$currentFile]['added'][] = $newLine++;
            } elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
                $result[$currentFile]['removed'][] = $oldLine++;
            } elseif (str_starts_with($line, ' ')) {
                $newLine++;
                $oldLine++;
            }
        }

        return $result;
    }
}
