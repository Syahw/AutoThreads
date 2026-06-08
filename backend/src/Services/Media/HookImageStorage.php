<?php

namespace AutoThreads\Services\Media;

use AutoThreads\Models\GeneratedPost;
use GuzzleHttp\Client;
use Psr\Http\Message\UploadedFileInterface;

class HookImageStorage
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    /** Meta Threads width limits (px). */
    private const MIN_WIDTH = 320;

    private const MAX_WIDTH = 1440;

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

        $contents = (string) $file->getStream()->getContents();
        $normalized = $this->normalizeImage($contents);

        if (strlen($normalized['contents']) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Image must be 8 MB or smaller after processing');
        }

        $filename = sprintf(
            '%d_%s.%s',
            $userId,
            bin2hex(random_bytes(16)),
            $normalized['extension']
        );
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($path, $normalized['contents']) === false) {
            throw new \RuntimeException('Failed to save image');
        }

        return ['filename' => $filename, 'mime' => $normalized['mime']];
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
                . 'Set PUBLIC_MEDIA_BASE_URL in .env to your ngrok HTTPS URL (same host as FRONTEND_URL when using ngrok on port 3000).'
            );
        }
    }

    /**
     * Verify Meta can fetch a real JPEG/PNG from the public URL before publishing.
     */
    public function validatePublicUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $this->assertReachableByMeta($url);

        $client = new Client([
            'verify' => guzzle_ssl_verify(),
            'timeout' => 20,
            'allow_redirects' => true,
            'http_errors' => false,
        ]);

        $response = $client->get($url, [
            'headers' => [
                'User-Agent' => 'facebookexternalhit/1.1',
                'Accept' => 'image/jpeg, image/png, */*',
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Hook image URL returned HTTP {$status}. "
                . 'Set PUBLIC_MEDIA_BASE_URL to your ngrok frontend URL and proxy /media in vite.config.js.'
            );
        }

        $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
        if (!in_array($contentType, ['image/jpeg', 'image/png'], true)) {
            throw new \RuntimeException(
                'Hook image URL must return JPEG or PNG (got '
                . ($contentType !== '' ? $contentType : 'unknown content type')
                . '). Meta received HTML instead of an image — check PUBLIC_MEDIA_BASE_URL and ngrok tunnel.'
            );
        }

        $body = (string) $response->getBody();
        if (@getimagesizefromstring($body) === false) {
            throw new \RuntimeException(
                'Hook image URL did not return valid image bytes. '
                . 'Ensure /media is proxied to the backend over HTTPS.'
            );
        }
    }

    /**
     * Re-encode to Meta-compatible JPEG/PNG and enforce width limits.
     *
     * @return array{contents: string, mime: string, extension: string}
     */
    private function normalizeImage(string $contents): array
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('PHP GD extension is required for hook image uploads');
        }

        $imageInfo = @getimagesizefromstring($contents);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('File must be a JPEG or PNG image');
        }

        $mime = $imageInfo['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new \InvalidArgumentException('Only JPEG and PNG images are supported');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new \InvalidArgumentException('Could not process image — try re-exporting as JPEG or PNG');
        }

        $image = $this->resizeToMetaLimits($image, $mime);
        $keepPng = $mime === 'image/png' && $this->imageHasTransparency($image);

        ob_start();
        if ($keepPng) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 6);
            $outMime = 'image/png';
            $extension = 'png';
        } else {
            imagejpeg($image, null, 90);
            $outMime = 'image/jpeg';
            $extension = 'jpg';
        }
        imagedestroy($image);

        $normalized = ob_get_clean();
        if (!is_string($normalized) || $normalized === '') {
            throw new \RuntimeException('Failed to encode image');
        }

        return [
            'contents' => $normalized,
            'mime' => $outMime,
            'extension' => $extension,
        ];
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function resizeToMetaLimits($image, string $mime)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Invalid image dimensions');
        }

        $targetWidth = $width;
        if ($width > self::MAX_WIDTH) {
            $targetWidth = self::MAX_WIDTH;
        } elseif ($width < self::MIN_WIDTH) {
            $targetWidth = self::MIN_WIDTH;
        }

        if ($targetWidth === $width) {
            return $image;
        }

        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * @param  \GdImage|resource  $image
     */
    private function imageHasTransparency($image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < min($width, 32); $x++) {
            for ($y = 0; $y < min($height, 32); $y++) {
                $rgba = imagecolorat($image, $x, $y);
                if (($rgba & 0x7F000000) >> 24) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isValidFilename(string $filename): bool
    {
        return (bool) preg_match('/^\d+_[a-f0-9]{32}\.(jpg|jpeg|png)$/i', $filename);
    }
}
