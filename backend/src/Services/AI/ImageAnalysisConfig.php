<?php

namespace AutoThreads\Services\AI;

/**
 * Environment-driven configuration for vision / image analysis requests.
 */
class ImageAnalysisConfig
{
    public function model(): string
    {
        return $_ENV['OPENAI_VISION_MODEL'] ?? $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini';
    }

    public function maxDimension(): int
    {
        return max(256, min(2048, (int) ($_ENV['OPENAI_IMAGE_MAX_DIMENSION'] ?? 1024)));
    }

    public function highDetailMaxDimension(): int
    {
        return max(512, min(2048, (int) ($_ENV['OPENAI_IMAGE_HIGH_DETAIL_MAX_DIMENSION'] ?? 1536)));
    }

    public function jpegQuality(): int
    {
        return max(50, min(95, (int) ($_ENV['OPENAI_IMAGE_JPEG_QUALITY'] ?? 82)));
    }

    public function maxUploadBytes(): int
    {
        return max(512 * 1024, (int) ($_ENV['OPENAI_IMAGE_MAX_BYTES'] ?? 4 * 1024 * 1024));
    }

    public function maxAttachments(): int
    {
        return max(1, min(10, (int) ($_ENV['OPENAI_IMAGE_MAX_ATTACHMENTS'] ?? 3)));
    }

    public function defaultDetail(): string
    {
        $detail = strtolower((string) ($_ENV['OPENAI_IMAGE_DETAIL'] ?? 'low'));

        return in_array($detail, ['low', 'high', 'auto'], true) ? $detail : 'low';
    }

    public function maxOutputTokens(): int
    {
        return (int) ($_ENV['OPENAI_MAX_TOKENS'] ?? 3000);
    }

    public function temperature(): float
    {
        return (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.75);
    }

    public function timeoutSeconds(): int
    {
        return max(30, (int) ($_ENV['OPENAI_VISION_TIMEOUT'] ?? 90));
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'model' => $this->model(),
            'max_dimension' => $this->maxDimension(),
            'high_detail_max_dimension' => $this->highDetailMaxDimension(),
            'jpeg_quality' => $this->jpegQuality(),
            'max_upload_bytes' => $this->maxUploadBytes(),
            'max_attachments' => $this->maxAttachments(),
            'default_detail' => $this->defaultDetail(),
        ];
    }
}
