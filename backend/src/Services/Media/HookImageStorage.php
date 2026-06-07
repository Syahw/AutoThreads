<?php

namespace AutoThreads\Services\Media;

use AutoThreads\Models\GeneratedPost;
use Psr\Http\Message\UploadedFileInterface;

class HookImageStorage
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    private string $uploadDir;

    public function __construct()
    {
        $this->uploadDir = dirname(__DIR__, 3) . '/storage/uploads/hook-images';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * @return array{filename: string, mime: string}
     */
    public function store(int $userId, UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Image upload failed');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Image must be 8 MB or smaller');
        }

        $stream = $file->getStream();
        $contents = (string) $stream->getContents();
        $imageInfo = @getimagesizefromstring($contents);

        if ($imageInfo === false) {
            throw new \InvalidArgumentException('File must be a JPEG or PNG image');
        }

        $mime = $imageInfo['mime'] ?? '';
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new \InvalidArgumentException('Only JPEG and PNG images are supported'),
        };

        $filename = sprintf('%d_%s.%s', $userId, bin2hex(random_bytes(16)), $extension);
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Failed to save image');
        }

        return ['filename' => $filename, 'mime' => $mime];
    }

    public function attachToPost(GeneratedPost $post, array $stored): void
    {
        $this->deleteForPost($post);

        $metadata = $post->metadata ?? [];
        $metadata['hook_image'] = [
            'filename' => $stored['filename'],
            'mime' => $stored['mime'],
            'uploaded_at' => date('c'),
        ];
        $post->metadata = $metadata;
        $post->save();
    }

    public function deleteForPost(GeneratedPost $post): void
    {
        $filename = $post->metadata['hook_image']['filename'] ?? null;
        $this->removeStoredFile(is_string($filename) ? $filename : null);

        $metadata = $post->metadata ?? [];
        unset($metadata['hook_image']);
        $post->metadata = $metadata;
        $post->save();
    }

    public function removeStoredFile(?string $filename): void
    {
        if (!is_string($filename) || $filename === '') {
            return;
        }

        $path = $this->pathForFilename($filename);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public function resolvePublicUrl(GeneratedPost $post): ?string
    {
        $filename = $post->metadata['hook_image']['filename'] ?? null;

        if (!is_string($filename) || $filename === '') {
            return null;
        }

        return $this->publicUrlForFilename($filename);
    }

    public function publicUrlForFilename(string $filename): string
    {
        if (!$this->isValidFilename($filename)) {
            throw new \InvalidArgumentException('Invalid image filename');
        }

        return public_media_base_url() . '/media/' . rawurlencode($filename);
    }

    public function pathForFilename(string $filename): ?string
    {
        if (!$this->isValidFilename($filename)) {
            return null;
        }

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;

        return is_file($path) ? $path : null;
    }

    public function assertReachableByMeta(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        if (str_starts_with($url, 'http://localhost') || str_starts_with($url, 'http://127.0.0.1')) {
            throw new \RuntimeException(
                'Hook image must be on a public HTTPS URL so Meta can fetch it. '
                . 'Set PUBLIC_MEDIA_BASE_URL in .env to your ngrok HTTPS base (same host as THREADS_REDIRECT_URI).'
            );
        }
    }

    private function isValidFilename(string $filename): bool
    {
        return (bool) preg_match('/^\d+_[a-f0-9]{32}\.(jpg|jpeg|png)$/i', $filename);
    }
}
