# Image Analysis & Vision Generation

AutoThreads can generate Threads content from a **reference image** using **GPT-4o Mini** and the **OpenAI Responses API**. Images are preprocessed on the server before upload to OpenAI to minimize token cost.

## Features

- Product identification from photos
- OCR / text extraction from labels and screenshots
- Screenshot and UI analysis
- Document / infographic understanding
- Thread generation grounded in niche + visual context

## How it works

1. User uploads a reference image on **Content generator** (optional).
2. Backend resizes/compresses the image (`ImagePreprocessor`).
3. `OpenAIResponsesClient` sends text + image(s) to `POST /v1/responses`.
4. Model returns thread content (+ optional `[EXTRACTED_TEXT]` block).
5. Output is humanized, scored, and stored as a draft with metadata.

Reference images are **not persisted** to disk — only processing metadata and extracted text are saved in `generated_posts.metadata`.

## API

### Get vision settings

```http
GET /api/v1/content/vision-settings
Authorization: Bearer {jwt}
```

**Response:**

```json
{
  "data": {
    "model": "gpt-4o-mini",
    "max_dimension": 1024,
    "high_detail_max_dimension": 1536,
    "jpeg_quality": 82,
    "max_upload_bytes": 4194304,
    "max_attachments": 3,
    "default_detail": "low"
  }
}
```

### Generate with image (multipart)

```http
POST /api/v1/content/generate
Authorization: Bearer {jwt}
Content-Type: multipart/form-data
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reference_image` | file | yes* | JPEG, PNG, WebP, or GIF |
| `reference_images[]` | file[] | alt | Multiple images (deduped by hash) |
| `niche_id` | string | no | Niche context |
| `category` | string | no | Default `general` |
| `tone` | string | no | Auto-rotate if empty |
| `affiliate_link_id` | string | no | Affiliate CTA |
| `high_detail` | `0`/`1` | no | High-detail vision (costly) |

\* Either `reference_image` or JSON-only body for text-only generation.

**Example (curl):**

```bash
curl -X POST "https://your-api/api/v1/content/generate" \
  -H "Authorization: Bearer YOUR_JWT" \
  -F "reference_image=@product.jpg" \
  -F "niche_id=2" \
  -F "category=product_recommendation" \
  -F "high_detail=0"
```

**Success response (201):**

```json
{
  "message": "Content generated from image",
  "data": {
    "id": 42,
    "content": "Reply 1:\n...",
    "metadata": {
      "image_generation": {
        "images": [{
          "width": 768,
          "height": 1024,
          "detail": "low",
          "estimated_tokens": 85,
          "content_hash": "abc123..."
        }],
        "estimated_image_tokens": 85,
        "usage": { "input_tokens": 1200, "output_tokens": 800, "total_tokens": 2000 }
      },
      "extracted_text": "Product name visible on label..."
    }
  },
  "image_analysis": {
    "generated_content": "Reply 1:\n...",
    "extracted_text": "Product name visible on label...",
    "estimated_image_tokens": 85,
    "usage": { "input_tokens": 1200, "output_tokens": 800, "total_tokens": 2000 },
    "processing": { "detail_requested": "low", "model": "gpt-4o-mini" }
  }
}
```

### Text-only generation (unchanged)

```http
POST /api/v1/content/generate
Content-Type: application/json

{
  "niche_id": 2,
  "category": "general",
  "tone": "casual",
  "variations": 1
}
```

## Configuration (.env)

```env
OPENAI_API_KEY=sk-...
OPENAI_VISION_MODEL=gpt-4o-mini
OPENAI_IMAGE_MAX_DIMENSION=1024
OPENAI_IMAGE_HIGH_DETAIL_MAX_DIMENSION=1536
OPENAI_IMAGE_JPEG_QUALITY=82
OPENAI_IMAGE_MAX_BYTES=4194304
OPENAI_IMAGE_MAX_ATTACHMENTS=3
OPENAI_IMAGE_DETAIL=low
OPENAI_VISION_TIMEOUT=90
```

| Variable | Default | Purpose |
|----------|---------|---------|
| `OPENAI_IMAGE_MAX_DIMENSION` | 1024 | Longest side for low-detail mode |
| `OPENAI_IMAGE_HIGH_DETAIL_MAX_DIMENSION` | 1536 | Longest side when user enables high detail |
| `OPENAI_IMAGE_JPEG_QUALITY` | 82 | JPEG compression (PNG kept if transparent) |
| `OPENAI_IMAGE_DETAIL` | low | Default OpenAI `detail` parameter |
| `OPENAI_IMAGE_MAX_BYTES` | 4MB | Reject oversized uploads |
| `OPENAI_IMAGE_MAX_ATTACHMENTS` | 3 | Max images per request |

## Cost minimization

1. **Resize before API** — Images scaled so longest side ≤ `OPENAI_IMAGE_MAX_DIMENSION` (default 1024px).
2. **Low detail by default** — ~85 tokens/image vs hundreds/thousands in high detail.
3. **High detail opt-in** — UI checkbox; uses larger max dimension only when enabled.
4. **JPEG re-encode** — Non-transparent images compressed at configurable quality.
5. **No disk storage** — Reference images processed in memory and discarded.
6. **Duplicate detection** — Same SHA-256 hash skipped in multi-image requests.
7. **Token logging** — Estimated image tokens + API usage logged to `storage/logs/app.log`.
8. **Crop support** — `ImagePreprocessor` accepts optional crop regions (API-ready for future UI).

### Token estimation

- **Low detail:** 85 tokens per image (fixed).
- **High detail:** `85 + 170 × tiles`, where `tiles = ceil(w/512) × ceil(h/512)`.

## Error handling

| Case | HTTP | Message |
|------|------|---------|
| Unsupported format | 422 | Use JPEG, PNG, WebP, or GIF |
| File too large | 422 | Exceeds max upload size |
| Invalid/corrupt image | 422 | Could not decode image |
| OpenAI rate limit | 429 | Rate limit reached |
| OpenAI auth failure | 502 | API key invalid |
| Variations + image | 422 | Use variations=1 with images |

## Files

| File | Role |
|------|------|
| `Services/Media/ImagePreprocessor.php` | Resize, compress, hash, token estimate |
| `Services/AI/ImageAnalysisConfig.php` | Env configuration |
| `Services/AI/OpenAIResponsesClient.php` | Responses API client |
| `Services/AI/ContentGenerator.php` | `generateWithImages()` pipeline |
| `Services/AI/PromptBuilder.php` | Vision prompt augmentation |
| `Controllers/ContentController.php` | Multipart upload handling |

## Requirements

- PHP **GD** extension (same as hook image uploads)
- Valid `OPENAI_API_KEY` with access to GPT-4o Mini and Responses API
