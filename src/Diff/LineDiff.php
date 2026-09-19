<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Diff;

class LineDiff
{
    /**
     * Returns unified diff lines for two code strings.
     *
     * @return array<array{type: string, text: string}>
     * ponytail: naive LCS via PHP array_diff, upgrade to proper Myers diff if quality matters
     */
    public static function compute(string $before, string $after): array
    {
        $oldLines = explode("\n", $before);
        $newLines = explode("\n", $after);

        // Simple heuristic: align by line equality, emit removed/added for mismatches
        $result = [];
        $i = 0;
        $j = 0;

        while ($i < count($oldLines) || $j < count($newLines)) {
            $old = $oldLines[$i] ?? null;
            $new = $newLines[$j] ?? null;

            if ($old === $new) {
                $result[] = ['type' => 'unchanged', 'text' => $old ?? ''];
                $i++;
                $j++;
            } elseif ($old === null) {
                $result[] = ['type' => 'added', 'text' => $new];
                $j++;
            } elseif ($new === null) {
                $result[] = ['type' => 'removed', 'text' => $old];
                $i++;
            } else {
                // Try to find the next matching line within a small lookahead window
                $lookahead = 4;
                $foundOld = false;
                $foundNew = false;

                for ($k = 1; $k <= $lookahead; $k++) {
                    if (isset($newLines[$j + $k]) && $newLines[$j + $k] === $old) {
                        // $old matches a later new line → current new lines are added
                        for ($l = 0; $l < $k; $l++) {
                            $result[] = ['type' => 'added', 'text' => $newLines[$j + $l]];
                        }
                        $j += $k;
                        $foundNew = true;
                        break;
                    }
                    if (isset($oldLines[$i + $k]) && $oldLines[$i + $k] === $new) {
                        // $new matches a later old line → current old lines are removed
                        for ($l = 0; $l < $k; $l++) {
                            $result[] = ['type' => 'removed', 'text' => $oldLines[$i + $l]];
                        }
                        $i += $k;
                        $foundOld = true;
                        break;
                    }
                }

                if (!$foundNew && !$foundOld) {
                    $result[] = ['type' => 'removed', 'text' => $old];
                    $result[] = ['type' => 'added', 'text' => $new];
                    $i++;
                    $j++;
                }
            }
        }

        return $result;
    }
}
