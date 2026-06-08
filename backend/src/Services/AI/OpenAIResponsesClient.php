<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Services\Media\ProcessedImage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * OpenAI Responses API client for multimodal (text + image) generation.
 */
class OpenAIResponsesClient
{
    private Client $http;
    private ImageAnalysisConfig $config;
    private ?LoggerInterface $logger;

    public function __construct(?ImageAnalysisConfig $config = null, ?LoggerInterface $logger = null)
    {
        $this->config = $config ?? new ImageAnalysisConfig();
        $this->logger = $logger;
        $this->http = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => $this->config->timeoutSeconds(),
            'verify' => guzzle_ssl_verify(),
        ]);
    }

    /**
     * @param  list<ProcessedImage>  $images
     * @return array{
     *   content: string,
     *   extracted_text: string|null,
     *   usage: array<string, int>,
     *   estimated_image_tokens: int,
     *   time_ms: int,
     *   model: string,
     *   response_id: string|null
     * }
     */
    public function generateWithImages(
        string $instructions,
        string $userText,
        array $images,
        ?string $detailOverride = null
    ): array {
        if ($images === []) {
            throw new \InvalidArgumentException('At least one image is required');
        }

        $content = [
            ['type' => 'input_text', 'text' => $userText],
        ];

        $estimatedImageTokens = 0;

        foreach ($images as $image) {
            $detail = $detailOverride ?? $image->detail;
            if (!in_array($detail, ['low', 'high', 'auto'], true)) {
                $detail = 'low';
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => $image->dataUrl,
                'detail' => $detail,
            ];

            $estimatedImageTokens += $image->estimatedTokens;
        }

        $payload = [
            'model' => $this->config->model(),
            'instructions' => $instructions,
            'input' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'max_output_tokens' => $this->config->maxOutputTokens(),
            'temperature' => $this->config->temperature(),
        ];

        $startTime = microtime(true);

        try {
            $response = $this->http->post('responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . ($_ENV['OPENAI_API_KEY'] ?? ''),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (ClientException $e) {
            throw $this->mapClientException($e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('Could not reach OpenAI API: ' . $e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode($response->getBody()->getContents(), true) ?? [];
        $timeMs = (int) ((microtime(true) - $startTime) * 1000);

        $text = $this->extractOutputText($data);
        $usage = $this->normalizeUsage($data['usage'] ?? []);
        $extractedText = $this->extractOcrBlock($text);

        $this->logger?->info('OpenAI vision response', [
            'model' => $this->config->model(),
            'images' => count($images),
            'estimated_image_tokens' => $estimatedImageTokens,
            'usage' => $usage,
            'time_ms' => $timeMs,
        ]);

        return [
            'content' => $text,
            'extracted_text' => $extractedText,
            'usage' => $usage,
            'estimated_image_tokens' => $estimatedImageTokens,
            'time_ms' => $timeMs,
            'model' => (string) ($data['model'] ?? $this->config->model()),
            'response_id' => $data['id'] ?? null,
        ];
    }

    private function extractOutputText(array $data): string
    {
        if (!empty($data['output_text']) && is_string($data['output_text'])) {
            return trim($data['output_text']);
        }

        $parts = [];
        foreach ($data['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'output_text' && !empty($block['text'])) {
                    $parts[] = $block['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int} */
    private function normalizeUsage(array $usage): array
    {
        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ];
    }

    /**
     * Pull OCR section if the model returned structured markers.
     */
    private function extractOcrBlock(string $content): ?string
    {
        if (preg_match('/\[EXTRACTED_TEXT\](.*?)\[\/EXTRACTED_TEXT\]/s', $content, $matches)) {
            $text = trim($matches[1]);

            return $text !== '' ? $text : null;
        }

        return null;
    }

    private function mapClientException(ClientException $e): \RuntimeException
    {
        $status = $e->getResponse()?->getStatusCode() ?? 0;
        $body = $e->getResponse()?->getBody()?->getContents() ?? '';
        $decoded = json_decode($body, true);
        $message = $decoded['error']['message'] ?? $e->getMessage();

        if ($status === 429) {
            return new \RuntimeException('OpenAI rate limit reached. Please wait and try again.');
        }

        if ($status === 413) {
            return new \RuntimeException('Image payload too large for OpenAI after preprocessing.');
        }

        if ($status === 401) {
            return new \RuntimeException('OpenAI API key is invalid or missing.');
        }

        return new \RuntimeException('OpenAI API error: ' . $message);
    }
}
