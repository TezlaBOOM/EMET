<?php

declare(strict_types=1);

namespace App\Contracts\Memory;

interface DocumentParserInterface
{
    /**
     * Parsuje dokument wejściowy (CSV, TSV, HTML/WWW, JSON, Markdown, tekst)
     * do postaci ustrukturyzowanego Markdownu z zachowaniem nagłówków tabel i metadanych.
     *
     * @return array{
     *     format: string,
     *     normalized_markdown: string,
     *     tables_extracted: int,
     *     rows_count: int,
     *     metadata: array<string, mixed>
     * }
     */
    public function parseToStructuredMarkdown(string $rawContent, ?string $mimeOrExtension = null, ?string $title = null): array;
}

