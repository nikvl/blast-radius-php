<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Viewer;

class HtmlViewerBuilder
{
    private string $templatePath;

    public function __construct()
    {
        $this->templatePath = dirname(__DIR__, 2) . '/assets/viewer.html.template';
    }

    public function build(string $outputDir, array $graph, array $codeIndex): void
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $template = file_get_contents($this->templatePath);

        $html = str_replace(
            ['{{VIEWER_TITLE}}', '{{GRAPH_DATA}}', '{{CODE_INDEX_DATA}}'],
            ['PHP UML Diff Viewer', json_encode($graph, \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG), json_encode($codeIndex, \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG)],
            $template
        );

        file_put_contents($outputDir . '/index.html', $html);
    }
}
