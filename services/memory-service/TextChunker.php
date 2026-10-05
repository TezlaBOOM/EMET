<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

class TextChunker
{
    /**
     * Dzieli tekst na fragmenty (chunki) z uwzględnieniem akapitów, nagłówków Markdown i nakładania się (overlap)
     *
     * @return list<array{title: string, content: string, index: int}>
     */
    public function chunk(string $text, int $maxChunkLength = 1000, int $overlap = 150): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Podział po nagłówkach (w tym na początku tekstu) lub podwójnych znakach nowej linii
        $sections = preg_split('/((?:^|\n)#{1,4}\s+[^\n]+|\n\n+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunks = [];
        $currentChunk = '';
        $currentTitle = '';
        $index = 0;

        foreach ($sections as $section) {
            $trimmed = trim($section);
            if ($trimmed === '') {
                continue;
            }

            // Jeśli to nagłówek Markdown, zaktualizuj aktualny tytuł
            if (preg_match('/^#{1,4}\s+(.+)$/u', $trimmed, $matches)) {
                $currentTitle = trim($matches[1]);
            }

            if (mb_strlen($currentChunk) + mb_strlen($section) > $maxChunkLength && mb_strlen($currentChunk) > 0) {
                $chunks[] = [
                    'title' => $currentTitle ?: ('Fragment ' . ($index + 1)),
                    'content' => trim($currentChunk),
                    'index' => $index++,
                ];

                // Zachowanie fragmentu z overlap
                $overlapContent = mb_substr($currentChunk, max(0, mb_strlen($currentChunk) - $overlap));
                $currentChunk = $overlapContent . "\n" . $section;
            } else {
                $currentChunk .= ($currentChunk === '' ? '' : "\n\n") . $section;
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = [
                'title' => $currentTitle ?: ('Fragment ' . ($index + 1)),
                'content' => trim($currentChunk),
                'index' => $index,
            ];
        }

        return $chunks;
    }
}
