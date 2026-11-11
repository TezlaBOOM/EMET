<?php

declare(strict_types=1);

namespace App\Contracts\Chat;

use App\Models\ChatArtifact;

interface MultimodalArtifactInterface
{
    /**
     * Wyodrębnia interaktywne artefakty (HTML, Mermaid, SVG, JSON, Chart, Code) z odpowiedzi agenta
     * i zapisuje je do panelu Live Preview Canvas.
     *
     * @return array<int, ChatArtifact>
     */
    public function extractAndStoreArtifacts(int|string|null $conversationId, int|string|null $messageId, string $responseText): array;

    /**
     * Przetwarza załącznik multimodalny (obraz Vision lub nagranie głosowe STT Whisper) do struktury wejściowej LLM.
     *
     * @param  array{mime_type: string, filename: string, content_base64?: string, transcript_hint?: string}  $attachment
     * @return array{
     *     modality: string,
     *     mime_type: string,
     *     filename: string,
     *     processed_prompt_addition: string,
     *     vision_data_url?: string
     * }
     */
    public function processMultimodalAttachment(array $attachment): array;
}
