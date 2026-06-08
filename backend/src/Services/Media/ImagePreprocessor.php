<?php

namespace AutoThreads\Services\Media;

use AutoThreads\Services\AI\ImageAnalysisConfig;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Resize, compress, and prepare images for OpenAI vision requests.
 */
class ImagePreprocessor
{
    private ImageAnalysisConfig $config;

    /** @var list<string> */
    private array $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(?ImageAnalysisConfig $config = null)
    {
        $this->config = $config ?? new ImageAnalysisConfig();
    }

    /**
     * Process one or more uploaded images for vision API usage.
     *
     * @param  UploadedFileInterface|UploadedFileInterface[]  $files
     * @param  array{high_detail?: bool, crop?: array{x: float, y: float, width: float, height: float}}  $options
     * @return list<ProcessedImage>
     */
    public function processUploads(UploadedFileInterface|array $files, array $options = []): array
    {
        $fileList = is_array($files) ? $files : [$files];
        $highDetail = (bool) ($options['high_detail'] ?? false);
        $maxDimension = $highDetail
            ? $this->config->highDetailMaxDimension()
            : $this->config->maxDimension();

        $processed = [];
        $seenHashes = [];

        foreach ($fileList as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Image upload failed (error code ' . $file->getError() . ')');
            }

            if ($file->getSize() > $this->config->maxUploadBytes()) {
                $maxMb = round($this->config->maxUploadBytes() / 1024 / 1024, 1);
                throw new \InvalidArgumentException("Image exceeds maximum upload size ({$maxMb} MB)");
            }

            $contents = (string) $file->getStream()->getContents();
            $item = $this->processBytes($contents, $maxDimension, $options['crop'] ?? null);

            if (isset($seenHashes[$item->contentHash])) {
                continue;
            }

            $seenHashes[$item->contentHash] = true;
            $item->detail = $highDetail ? 'high' : $this->config->defaultDetail();
            $item->estimatedTokens = $this->estimateImageTokens($item->width, $item->height, $item->detail);
            $processed[] = $item;

            if (count($processed) >= $this->config->maxAttachments()) {
                break;
            }
        }

        if ($processed === []) {
            throw new \InvalidArgumentException('No valid images were provided');
        }

        return $processed;
    }

    /**
     * @param  array{x: float, y: float, width: float, height: float}|null  $crop  Fractions 0–1
     */
    public function processBytes(string $contents, ?int $maxDimension = null, ?array $crop = null): ProcessedImage
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('PHP GD extension is required for image processing');
        }

        $maxDimension ??= $this->config->maxDimension();

        $imageInfo = @getimagesizefromstring($contents);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Unsupported or corrupt image file. Use JPEG, PNG, WebP, or GIF.');
        }

        $mime = $imageInfo['mime'] ?? '';
        if (!in_array($mime, $this->allowedMime, true)) {
            throw new \InvalidArgumentException('Unsupported image format. Allowed: JPEG, PNG, WebP, GIF.');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new \InvalidArgumentException('Could not decode image');
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        if ($crop !== null) {
            $image = $this->applyCrop($image, $crop);
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
        }

        $image = $this->resizeLongestSide($image, $maxDimension, $mime);
        $width = imagesx($image);
        $height = imagesy($image);

        $keepPng = $mime === 'image/png' && $this->imageHasTransparency($image);

        ob_start();
        if ($keepPng) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 6);
            $outMime = 'image/png';
        } else {
            imagejpeg($image, null, $this->config->jpegQuality());
            $outMime = 'image/jpeg';
        }
        imagedestroy($image);

        $output = ob_get_clean();
        if (!is_string($output) || $output === '') {
            throw new \RuntimeException('Failed to encode processed image');
        }

        if (strlen($output) > $this->config->maxUploadBytes()) {
            throw new \InvalidArgumentException('Processed image is still too large. Try a smaller source image.');
        }

        return new ProcessedImage(
            contents: $output,
            mime: $outMime,
            width: $width,
            height: $height,
            originalWidth: $imageInfo[0],
            originalHeight: $imageInfo[1],
            contentHash: hash('sha256', $output),
            dataUrl: 'data:' . $outMime . ';base64,' . base64_encode($output),
        );
    }

    public function estimateImageTokens(int $width, int $height, string $detail = 'low'): int
    {
        $detail = strtolower($detail);

        if ($detail === 'low') {
            return 85;
        }

        $tiles = (int) (ceil($width / 512) * ceil($height / 512));

        return 85 + (170 * max(1, $tiles));
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function resizeLongestSide($image, int $maxDimension, string $mime)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / $longest;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

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
     * @param  array{x: float, y: float, width: float, height: float}  $crop
     * @return \GdImage|resource
     */
    private function applyCrop($image, array $crop)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $x = (int) round(max(0, min(1, $crop['x'] ?? 0)) * $width);
        $y = (int) round(max(0, min(1, $crop['y'] ?? 0)) * $height);
        $w = (int) round(max(0.01, min(1, $crop['width'] ?? 1)) * $width);
        $h = (int) round(max(0.01, min(1, $crop['height'] ?? 1)) * $height);

        $w = min($w, $width - $x);
        $h = min($h, $height - $y);

        $cropped = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        imagedestroy($image);

        if ($cropped === false) {
            throw new \InvalidArgumentException('Invalid crop region for image');
        }

        return $cropped;
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
}

/**
 * Normalized image ready for OpenAI vision input.
 */
class ProcessedImage
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
        public readonly int $originalWidth,
        public readonly int $originalHeight,
        public readonly string $contentHash,
        public readonly string $dataUrl,
        public string $detail = 'low',
        public int $estimatedTokens = 85,
    ) {
    }

    /** @return array<string, mixed> */
    public function toMetadata(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'original_width' => $this->originalWidth,
            'original_height' => $this->originalHeight,
            'mime' => $this->mime,
            'bytes' => strlen($this->contents),
            'content_hash' => $this->contentHash,
            'detail' => $this->detail,
            'estimated_tokens' => $this->estimatedTokens,
        ];
    }
}
