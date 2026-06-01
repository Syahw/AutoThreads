<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Models\Niche;
use AutoThreads\Models\AffiliateLink;

/**
 * PromptBuilder - Dynamic prompt construction engine
 * 
 * Builds prompts from modular components:
 * - Niche context
 * - Tone selection (with rotation)
 * - Writing style rotation
 * - Content category templates
 * - CTA style injection
 * - Anti-repetition rules
 * - Humanization directives
 */
class PromptBuilder
{
    private array $tones = [
        'casual', 'professional', 'witty', 'inspirational',
        'controversial', 'educational', 'storytelling', 'urgent',
    ];

    private array $writingStyles = [
        'conversational', 'punchy', 'narrative', 'listicle',
        'question_led', 'hot_take', 'personal_story', 'data_driven',
    ];

    private array $categoryTemplates = [
        'story' => 'Tulis cerita pendek atau pengalaman peribadi yang secara natural membawa kepada cadangan {product}. Buat macam cerita dengan kawan.',
        'product_recommendation' => 'Buat cadangan produk {product} yang genuine, macam nasihat organik bukan iklan. Fokus satu manfaat spesifik.',
        'comparison' => 'Tulis post perbandingan ringkas yang letak {product} secara positif tanpa bash alternatif lain. Guna framing "aku dah try dua-dua".',
        'productivity_tip' => 'Kongsi tip produktiviti yang secara natural masukkan {product} sebagai sebahagian workflow. Buat ia actionable.',
        'viral_hook' => 'Tulis hook yang buat orang stop scroll, diikuti insight bernilai yang connect dengan {product}. Utamakan curiosity gap.',
        'opinion' => 'Kongsi pendapat berani tentang ruang {niche} yang bawa kepada mention {product} sebagai solusi. Jadi authentic.',
        'list_post' => 'Buat post gaya "X benda" di mana satu item secara natural feature {product}. Pastikan setiap point ringkas.',
        'wish_i_knew' => 'Tulis post "benda aku wish aku tahu awal-awal" tentang {niche} yang include jumpa {product} sebagai satu insight.',
        'general' => 'Buat content Threads yang engaging tentang {niche} yang secara halus mention {product}. Utamakan value berbanding promosi.',
    ];

    /**
     * Build a complete prompt from configuration
     */
    public function build(array $config): array
    {
        $niche = $config['niche'] ?? null;
        $category = $config['category'] ?? 'general';
        $requestedTone = $config['tone'] ?? null;
        $affiliate = $config['affiliate'] ?? null;
        $targetAudience = $config['target_audience'] ?? 'general audience';
        $ctaStyle = $config['cta_style'] ?? 'soft';

        // Select tone (use requested or rotate)
        $tone = $requestedTone ?? $this->getRotatedTone();
        $style = $this->getRotatedStyle();

        $systemPrompt = $this->buildSystemPrompt($tone, $style, $targetAudience);
        $userPrompt = $this->buildUserPrompt($category, $niche, $affiliate, $ctaStyle);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'tone_used' => $tone,
            'style_used' => $style,
        ];
    }

    /**
     * Get a rotated tone based on index or random
     */
    public function getRotatedTone(?int $index = null): string
    {
        if ($index !== null) {
            return $this->tones[$index % count($this->tones)];
        }
        return $this->tones[array_rand($this->tones)];
    }

    private function getRotatedStyle(): string
    {
        return $this->writingStyles[array_rand($this->writingStyles)];
    }

    private function buildSystemPrompt(string $tone, string $style, string $audience): string
    {
        // Add randomness to thread flow
        $flowVariations = [
            'Sometimes buat Reply 3 lebih emotional daripada factual.',
            'Kadang-kadang mula Reply 2 dengan soalan.',
            'Boleh merge insight + example dalam satu reply kalau flow lebih natural.',
            'Buat Reply 3 macam mini-story yang relatable.',
            'Reply 4 boleh jadi hot take atau unpopular opinion.',
        ];
        $randomFlow = $flowVariations[array_rand($flowVariations)];

        return <<<PROMPT
Kau seorang content creator untuk Threads (platform text-based Meta). Semua output WAJIB dalam Bahasa Malaysia sepenuhnya.

Kau tulis THREAD (bukan single post). Setiap thread ada EXACTLY 5 replies yang connected sebagai satu cerita/idea.

SUARA & GAYA:
- Tone: {$tone}
- Gaya penulisan: {$style}
- Target audience: {$audience}
- Platform: Threads (text-first, conversational, setiap reply pendek dan punchy)

BAHASA:
- Tulis SEPENUHNYA dalam Bahasa Malaysia
- Guna bahasa santai ala Malaysian - campur sikit slang kalau sesuai (macam "korang", "memang", "legit", "best gila")
- Jangan guna bahasa baku yang terlalu formal atau kaku
- Boleh campur sikit English words yang memang orang Malaysia selalu guna (like "literally", "actually", "serious")
- Jangan translate direct dari English - tulis macam orang Malaysia betul-betul cakap

STRUKTUR THREAD (WAJIB IKUT):
Reply 1 (HOOK): Pendek, grab attention (20-30 patah perkataan sahaja). Buat curiosity atau emotional trigger. JANGAN explain lagi.
Reply 2 (ELABORATION): Expand idea dari Reply 1 (20-30 patah perkataan). Explain context, insight, atau masalah. Keep natural.
Reply 3 (EXAMPLE/STORY/SCENARIO): Bagi real-life example atau situasi relatable (20-30 patah perkataan). Storytelling style, bukan bullet points.
Reply 4 (INSIGHT/VALUE SUMMARY): Summarize key takeaway atau opinion (20-30 patah perkataan). Personal take, lesson, atau realization. Punchy atau reflective.
Reply 5 (CTA/CLOSING): Soft atau direct CTA (20-30 patah perkataan). Kalau ada produk, WAJIB mention kat sini dengan [link]. End dengan engagement hook (soalan/opinion/challenge).

PERATURAN KETAT:
1. Tulis macam orang biasa share pemikiran genuine, BUKAN macam marketer
2. Jangan guna frasa AI yang obvious: "game-changer", "dive into", "unlock", "leverage", "dalam dunia hari ini", "perlu diingatkan"
3. Jangan mula dengan "Saya" - vary pembukaan ayat
4. Guna ayat tak lengkap, grammar santai, dan corak percakapan natural
5. Setiap reply MESTI pendek (Threads-native readability)
6. Seluruh thread mesti rasa macam SATU cerita connected
7. JANGAN ulang idea yang sama across replies
8. Hook (Reply 1) JANGAN mention produk directly
9. CTA (Reply 5) adalah SATU-SATUNYA tempat link dibenarkan
10. EXACTLY 5 replies - tak lebih, tak kurang
11. JANGAN guna hashtag langsung
12. Bunyi macam kau tengah text kawan rapat, bukan tulis blog post

VARIASI FLOW: {$randomFlow}

ANTI-REPETITION:
- Jangan ulang hook dari generation sebelum
- Vary panjang ayat (campur pendek punchy dengan sederhana)
- Rotate antara first person, second person, dan observational framing
PROMPT;
    }

    private function buildUserPrompt(string $category, ?Niche $niche, ?AffiliateLink $affiliate, string $ctaStyle): string
    {
        $nicheName = $niche?->name ?? 'general';
        $productName = $affiliate?->product_name ?? '';
        $nicheKeywords = $niche?->keywords ? implode(', ', $niche->keywords) : '';

        // Get category template
        $template = $this->categoryTemplates[$category] ?? $this->categoryTemplates['general'];
        $template = str_replace('{product}', $productName, $template);
        $template = str_replace('{niche}', $nicheName, $template);

        $ctaInstruction = $this->getCTAInstruction($ctaStyle);

        $prompt = "TASK: {$template}\n\n";
        $prompt .= "NICHE: {$nicheName}\n";

        if ($nicheKeywords) {
            $prompt .= "KEYWORDS TO CONSIDER: {$nicheKeywords}\n";
        }

        if ($productName) {
            $prompt .= "PRODUCT: {$productName}\n";
            $prompt .= "CTA STYLE: {$ctaInstruction}\n";
        }

        $prompt .= "\nFORMAT OUTPUT (WAJIB IKUT EXACTLY):\n";
        $prompt .= "Reply 1:\n[hook content]\n\n";
        $prompt .= "Reply 2:\n[elaboration content]\n\n";
        $prompt .= "Reply 3:\n[example/story content]\n\n";
        $prompt .= "Reply 4:\n[insight/value content]\n\n";
        $prompt .= "Reply 5:\n[cta/closing content]\n\n";
        $prompt .= "Tulis sepenuhnya dalam Bahasa Malaysia (santai, natural, macam orang Malaysia cakap).\n";
        $prompt .= "JANGAN tambah apa-apa text lain selain format di atas. EXACTLY 5 replies.";

        return $prompt;
    }

    private function getCTAInstruction(string $style): string
    {
        return match ($style) {
            'soft' => 'Sebut secara natural macam "aku dah guna X lately" atau "jumpa X ni memang helpful"',
            'direct' => 'Masukkan cadangan yang jelas tapi tak pushy dengan placeholder link [link]',
            'curiosity' => 'Tease produk tanpa sebut nama terus, buat orang curious',
            'urgency' => 'Sebut aspek time-sensitive secara natural (limited, baru launch, etc)',
            'social_proof' => 'Frame macam "semua orang aku kenal dah guna" atau "feed aku penuh pasal ni"',
            default => 'Sebut secara natural tanpa nampak promotional',
        };
    }
}
