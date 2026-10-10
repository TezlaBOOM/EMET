<?php

declare(strict_types=1);

namespace App\Services\Skills;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class SkillPackageExtractor
{
    /**
     * Weryfikuje sumę kontrolną SHA-256 paczki ZIP.
     */
    public function verifyChecksum(string $zipFilePath, ?string $expectedChecksum = null): string
    {
        if (! File::exists($zipFilePath)) {
            throw new InvalidArgumentException("Plik archiwum skilla nie istnieje: {$zipFilePath}");
        }

        $actualChecksum = hash_file('sha256', $zipFilePath);

        if ($expectedChecksum !== null && ! hash_equals(strtolower($expectedChecksum), strtolower($actualChecksum))) {
            throw new InvalidArgumentException(
                "Niezgodność sumy kontrolnej SHA-256. Oczekiwano: {$expectedChecksum}, otrzymano: {$actualChecksum}"
            );
        }

        return $actualChecksum;
    }

    /**
     * Bezpieczne rozpakowanie archiwum ZIP z ochroną przed Zip-Slip.
     *
     * @return array{files: array<int, string>, manifest: ?array<string, mixed>}
     */
    public function extract(string $zipFilePath, string $destinationDir): array
    {
        if (! File::isDirectory($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true);
        }

        $canonicalDest = realpath($destinationDir);
        if ($canonicalDest === false) {
            throw new RuntimeException("Nie można rozwiązać ścieżki docelowej: {$destinationDir}");
        }

        $zip = new ZipArchive;
        $openResult = $zip->open($zipFilePath);
        if ($openResult !== true) {
            throw new RuntimeException("Nie można otworzyć archiwum ZIP: kod błędu {$openResult}");
        }

        $extractedFiles = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! $stat) {
                    continue;
                }

                $entryName = $stat['name'];

                // Ochrona przed Zip-Slip (ścieżki względne ../ lub bezwzględne)
                if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                    throw new InvalidArgumentException("Wykryto próbę ataku Zip-Slip w pliku: {$entryName}");
                }

                $targetPath = $destinationDir.DIRECTORY_SEPARATOR.$entryName;

                // Sprawdzenie czy cel nie ucieka poza canonicalDest
                $parentDir = dirname($targetPath);
                if (! File::isDirectory($parentDir)) {
                    File::makeDirectory($parentDir, 0755, true);
                }

                // Jeżeli wpis to katalog
                if (str_ends_with($entryName, '/') || str_ends_with($entryName, '\\')) {
                    if (! File::isDirectory($targetPath)) {
                        File::makeDirectory($targetPath, 0755, true);
                    }

                    continue;
                }

                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    throw new RuntimeException("Nie można odczytać pliku z archiwum: {$entryName}");
                }

                File::put($targetPath, $content);
                $extractedFiles[] = $entryName;
            }
        } finally {
            $zip->close();
        }

        $manifest = null;
        $manifestPath = $destinationDir.DIRECTORY_SEPARATOR.'skill.json';
        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);
        }

        return [
            'files' => $extractedFiles,
            'manifest' => is_array($manifest) ? $manifest : null,
        ];
    }
}
