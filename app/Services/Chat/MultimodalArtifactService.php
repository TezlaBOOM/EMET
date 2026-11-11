<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Contracts\Chat\MultimodalArtifactInterface;
use App\Models\ChatArtifact;

class MultimodalArtifactService implements MultimodalArtifactInterface
{
    public function extractAndStoreArtifacts(int|string|null $conversationId, int|string|null $messageId, string $responseText): array
    {
        $created = [];
        $convIdStr = $conversationId !== null ? (string) $conversationId : null;
        $msgIdStr = $messageId !== null ? (string) $messageId : null;

        // 1. Wykrywanie jawnych tagów <artifact title="..." type="...">...</artifact>
        if (preg_match_all('/<artifact\s+([^>]+)>(.*?)<\/artifact>/is', $responseText, $tagMatches, PREG_SET_ORDER)) {
            foreach ($tagMatches as $index => $match) {
                $attrString = $match[1];
                $body = trim($match[2]);

                $title = 'Artefakt #'.($index + 1);
                if (preg_match('/title=["\']([^"\']+)["\']/i', $attrString, $mTitle)) {
                    $title = $mTitle[1];
                }

                $type = 'html';
                if (preg_match('/title=["\']([^"\']+)["\']/i', $attrString) && preg_match('/type=["\']([^"\']+)["\']/i', $attrString, $mType)) {
                    $type = strtolower($mType[1]);
                } elseif (preg_match('/type=["\']([^"\']+)["\']/i', $attrString, $mType)) {
                    $type = strtolower($mType[1]);
                }

                $created[] = ChatArtifact::create([
                    'conversation_id' => $convIdStr,
                    'message_id' => $msgIdStr,
                    'title' => $title,
                    'artifact_type' => $type,
                    'language' => $type,
                    'content' => $body,
                    'metadata' => ['source' => 'artifact_tag'],
                    'version' => 1,
                ]);
            }
        }

        // 2. Wykrywanie bloków kodu ```lang ... ``` dla Mermaid, HTML, SVG, JSON, Chart
        if (preg_match_all('/```(html|mermaid|svg|json|chart|javascript|python|sql)\s*\n(.*?)```/is', $responseText, $codeMatches, PREG_SET_ORDER)) {
            foreach ($codeMatches as $idx => $match) {
                $lang = strtolower(trim($match[1]));
                $code = trim($match[2]);
                if ($code === '') {
                    continue;
                }

                $artifactType = match ($lang) {
                    'mermaid' => 'mermaid',
                    'html' => 'html',
                    'svg' => 'svg',
                    'chart' => 'chart',
                    'json' => 'json',
                    default => 'code',
                };

                $created[] = ChatArtifact::create([
                    'conversation_id' => $convIdStr,
                    'message_id' => $msgIdStr,
                    'title' => sprintf('Podgląd %s #%d', strtoupper($lang), $idx + 1),
                    'artifact_type' => $artifactType,
                    'language' => $lang,
                    'content' => $code,
                    'metadata' => ['source' => 'fenced_block', 'lines' => substr_count($code, "\n") + 1],
                    'version' => 1,
                ]);
            }
        }

        return $created;
    }

    public function processMultimodalAttachment(array $attachment): array
    {
        $mimeType = strtolower(trim((string) ($attachment['mime_type'] ?? 'application/octet-stream')));
        $filename = (string) ($attachment['filename'] ?? 'attachment.bin');
        $base64 = (string) ($attachment['content_base64'] ?? '');

        if (str_starts_with($mimeType, 'image/')) {
            $dataUrl = $base64 !== '' ? "data:{$mimeType};base64,{$base64}" : "data:{$mimeType};base64,";

            return [
                'modality' => 'vision',
                'mime_type' => $mimeType,
                'filename' => $filename,
                'processed_prompt_addition' => sprintf('[Załącznik obrazu Vision: %s (%s)]', $filename, $mimeType),
                'vision_data_url' => $dataUrl,
            ];
        }

        if (str_starts_with($mimeType, 'audio/')) {
            $transcript = (string) ($attachment['transcript_hint'] ?? 'Transkrypcja głosowa STT (Whisper): polecenie głosowe użytkownika.');

            return [
                'modality' => 'audio_stt',
                'mime_type' => $mimeType,
                'filename' => $filename,
                'processed_prompt_addition' => sprintf('[Transkrypcja audio (%s)]: %s', $filename, $transcript),
            ];
        }

        return [
            'modality' => 'document',
            'mime_type' => $mimeType,
            'filename' => $filename,
            'processed_prompt_addition' => sprintf('[Załącznik dokumentu: %s (%s)]', $filename, $mimeType),
        ];
    }
}
