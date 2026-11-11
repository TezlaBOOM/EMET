<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Memory\DocumentParserInterface;

class DocumentParserService implements DocumentParserInterface
{
    public function parseToStructuredMarkdown(string $rawContent, ?string $mimeOrExtension = null, ?string $title = null): array
    {
        $trimmed = trim($rawContent);
        $format = $this->detectFormat($trimmed, $mimeOrExtension);
        $tablesExtracted = 0;
        $rowsCount = 0;
        $normalized = $trimmed;

        if ($format === 'csv' || $format === 'tsv') {
            $delimiter = $format === 'tsv' ? "\t" : ',';
            $parsedCsv = $this->convertCsvToMarkdownTable($trimmed, $delimiter, $title);
            $normalized = $parsedCsv['markdown'];
            $tablesExtracted = 1;
            $rowsCount = $parsedCsv['rows'];
        } elseif ($format === 'html') {
            $parsedHtml = $this->convertHtmlToMarkdownWithTables($trimmed, $title);
            $normalized = $parsedHtml['markdown'];
            $tablesExtracted = $parsedHtml['tables'];
            $rowsCount = $parsedHtml['rows'];
        } elseif ($format === 'json') {
            $parsedJson = $this->convertJsonToMarkdown($trimmed, $title);
            $normalized = $parsedJson['markdown'];
            $tablesExtracted = $parsedJson['tables'];
            $rowsCount = $parsedJson['rows'];
        }

        return [
            'format' => $format,
            'normalized_markdown' => $normalized,
            'tables_extracted' => $tablesExtracted,
            'rows_count' => $rowsCount,
            'metadata' => [
                'detected_format' => $format,
                'tables_extracted' => $tablesExtracted,
                'rows_count' => $rowsCount,
                'original_length' => strlen($rawContent),
            ],
        ];
    }

    private function detectFormat(string $content, ?string $hint): string
    {
        $h = strtolower(trim((string) $hint));
        if (in_array($h, ['csv', 'text/csv'], true)) {
            return 'csv';
        }
        if (in_array($h, ['tsv', 'text/tab-separated-values'], true)) {
            return 'tsv';
        }
        if (in_array($h, ['html', 'htm', 'text/html'], true) || preg_match('/<table[\s>]|<html[\s>]|<body[\s>]/i', $content)) {
            return 'html';
        }
        if (in_array($h, ['json', 'application/json'], true) || ((str_starts_with($content, '[') || str_starts_with($content, '{')) && json_decode($content, true) !== null)) {
            return 'json';
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $content)), fn (string $l) => $l !== ''));
        if (count($lines) >= 2 && substr_count($lines[0], ',') >= 1 && substr_count($lines[0], ',') === substr_count($lines[1], ',')) {
            return 'csv';
        }

        return 'markdown';
    }

    /**
     * @return array{markdown: string, rows: int}
     */
    private function convertCsvToMarkdownTable(string $csvContent, string $delimiter, ?string $title): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_map(fn ($cell) => trim((string) $cell), str_getcsv($line, $delimiter, '"', '\\'));
        }

        if (empty($rows)) {
            return ['markdown' => $csvContent, 'rows' => 0];
        }

        $headers = $rows[0];
        $md = [];
        if ($title) {
            $md[] = '# '.$title;
            $md[] = '';
        }

        $md[] = '| '.implode(' | ', $headers).' |';
        $md[] = '| '.implode(' | ', array_fill(0, count($headers), '---')).' |';

        $dataRowsCount = 0;
        for ($i = 1, $n = count($rows); $i < $n; $i++) {
            $row = array_pad($rows[$i], count($headers), '');
            $md[] = '| '.implode(' | ', array_slice($row, 0, count($headers))).' |';
            $dataRowsCount++;
        }

        return [
            'markdown' => implode("\n", $md),
            'rows' => $dataRowsCount,
        ];
    }

    /**
     * @return array{markdown: string, tables: int, rows: int}
     */
    private function convertHtmlToMarkdownWithTables(string $html, ?string $title): array
    {
        $tablesCount = 0;
        $totalRows = 0;

        $processed = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is', function (array $match) use (&$tablesCount, &$totalRows): string {
            $tablesCount++;
            $tableInner = $match[1];
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableInner, $trMatches);

            $mdRows = [];
            foreach ($trMatches[1] ?? [] as $rowIdx => $trContent) {
                preg_match_all('/<(?:th|td)[^>]*>(.*?)<\/(?:th|td)>/is', $trContent, $cellMatches);
                $cells = array_map(fn ($c) => trim(strip_tags((string) $c)), $cellMatches[1] ?? []);
                if (empty($cells)) {
                    continue;
                }
                $mdRows[] = '| '.implode(' | ', $cells).' |';
                if ($rowIdx === 0) {
                    $mdRows[] = '| '.implode(' | ', array_fill(0, count($cells), '---')).' |';
                } else {
                    $totalRows++;
                }
            }

            return "\n\n".implode("\n", $mdRows)."\n\n";
        }, $html) ?? $html;

        $processed = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $processed) ?? $processed;
        $processed = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $processed) ?? $processed;
        $processed = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n### $1\n", $processed) ?? $processed;
        $cleanText = trim(strip_tags($processed));

        if ($title && ! str_starts_with($cleanText, '#')) {
            $cleanText = "# {$title}\n\n".$cleanText;
        }

        return [
            'markdown' => $cleanText,
            'tables' => $tablesCount,
            'rows' => $totalRows,
        ];
    }

    /**
     * @return array{markdown: string, tables: int, rows: int}
     */
    private function convertJsonToMarkdown(string $json, ?string $title): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return ['markdown' => $json, 'tables' => 0, 'rows' => 0];
        }

        if (array_is_list($decoded) && ! empty($decoded) && is_array($decoded[0])) {
            $headers = array_keys($decoded[0]);
            $md = [];
            if ($title) {
                $md[] = '# '.$title;
                $md[] = '';
            }
            $md[] = '| '.implode(' | ', $headers).' |';
            $md[] = '| '.implode(' | ', array_fill(0, count($headers), '---')).' |';

            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $vals = [];
                foreach ($headers as $h) {
                    $v = $row[$h] ?? '';
                    $vals[] = is_scalar($v) ? (string) $v : (json_encode($v, JSON_UNESCAPED_UNICODE) ?: '');
                }
                $md[] = '| '.implode(' | ', $vals).' |';
            }

            return [
                'markdown' => implode("\n", $md),
                'tables' => 1,
                'rows' => count($decoded),
            ];
        }

        return [
            'markdown' => ($title ? "# {$title}\n\n" : '')."```json\n".json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```",
            'tables' => 0,
            'rows' => count($decoded),
        ];
    }
}

