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

    /** Templates when no product / affiliate — sharing & engagement only */
    private array $sharingCategoryTemplates = [
        'story' => 'Tulis cerita pendek atau pengalaman peribadi tentang {niche}. Kongsi macam cerita dengan kawan — tiada jualan.',
        'product_recommendation' => 'Kongsi satu tip atau discovery tentang {niche} yang genuinely helpful. Bukan iklan produk.',
        'comparison' => 'Tulis perbandingan ringkas atau perspektif dua pendekatan dalam {niche}. Guna framing "aku dah try dua-dua".',
        'productivity_tip' => 'Kongsi tip produktiviti tentang {niche}. Buat ia actionable dan relatable.',
        'viral_hook' => 'Tulis hook yang buat orang stop scroll, diikuti insight bernilai tentang {niche}. Utamakan curiosity gap.',
        'opinion' => 'Kongsi pendapat berani tentang {niche}. Jadi authentic — ajak perbincangan, bukan jualan.',
        'list_post' => 'Buat post gaya "X benda" tentang {niche}. Pastikan setiap point ringkas dan bernilai.',
        'wish_i_knew' => 'Tulis post "benda aku wish aku tahu awal-awal" tentang {niche}. Tutup dengan refleksi atau soalan.',
        'general' => 'Buat content Threads yang engaging tentang {niche}. Utamakan value, storytelling, atau hot take — bukan promosi.',
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

        $replyCount = $this->getRandomReplyCount();

        $systemPrompt = $this->buildSystemPrompt($tone, $style, $targetAudience, $replyCount);
        $userPrompt = $this->buildUserPrompt($category, $niche, $affiliate, $ctaStyle, $replyCount);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'tone_used' => $tone,
            'style_used' => $style,
            'reply_count' => $replyCount,
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

    private function getRandomReplyCount(): int
    {
        return random_int(5, 7);
    }

    private function buildSystemPrompt(string $tone, string $style, string $audience, int $replyCount): string
    {
        // Add randomness to thread flow
        $flowVariations = [
            'Kadang thread stop awal kalau point dah kuat.',
            'Boleh tambah extra reply kalau storytelling perlukan buildup.',
            'Ada thread yang lebih pendek dan straight to the point.',
            'Kadang Reply terakhir lebih reflective daripada CTA.',
            'Boleh buat pacing slow untuk emotional topic.',
            'Kadang thread rasa macam sembang random tapi connected.',
            'Tak semua thread perlu hard closing.',
        ];

        $randomFlow = $flowVariations[array_rand($flowVariations)];

        return <<<PROMPT
        Kau seorang content creator untuk Threads (platform text-based Meta). Semua output WAJIB dalam Bahasa Malaysia sepenuhnya.

        Kau tulis THREAD (bukan single post). Setiap thread mesti rasa natural, connected, dan conversational.

        PANJANG THREAD (GENERATION INI):
        - Tulis TEPAT {$replyCount} replies untuk thread ini
        - Range biasa: 5–7 replies (berbeza setiap generation)
        - Pilih flow berdasarkan kesesuaian topik dalam {$replyCount} replies
        - Jangan tambah atau kurangkan bilangan reply

        SUARA & GAYA:
        - Tone: {$tone}
        - Gaya penulisan: {$style}
        - Target audience: {$audience}
        - Platform: Threads (text-first, conversational, setiap reply pendek dan punchy)

        BAHASA:
        - Tulis SEPENUHNYA dalam Bahasa Malaysia
        - Guna bahasa santai ala Malaysian — natural tapi EJAAN BETUL (tiada typo)
        - WAJIB eja perkataan dengan betul: "biasa", "dengan", "riang", "kecil", "special" — JANGAN salah huruf macam "bdiasa", "ngan rdiang", "specdial", "kecik" melainkan perkataan itu memang slang standard (contoh: "je", "dah", "tak", "nak")
        - Boleh guna perkataan santai standard: "korang", "aku", "memang", "best gila", "seriously", "legit"
        - Jangan guna bahasa baku berlebihan atau ayat kaku macam essay
        - Boleh masukkan English words yang orang Malaysia memang guna (literally, actually) — jangan direct translate dari English
        - Semak semula setiap reply: tiada missing letter, tiada random typo, grammar masih natural

        FLOW THREAD:
        Reply 1:
        - WAJIB jadi hook
        - Pendek dan grab attention
        - Buat curiosity, emotional trigger, atau relatable thought
        - JANGAN explain semua terus
        - Sekitar 20-40 patah perkataan

        Middle Replies:
        - Expand idea slowly
        - Boleh mix:
        • elaboration
        • personal observation
        • relatable situation
        • mini-story
        • emotional reflection
        • hot take
        • realization
        - Tak perlu ikut format rigid
        - Biarkan flow rasa natural macam orang tengah bercakap

        Last Reply:
        - Boleh jadi CTA
        - Boleh jadi reflective ending
        - Boleh jadi open-ended question
        - Boleh jadi strong final thought
        - Kalau ada produk/link, WAJIB mention hanya di last reply menggunakan [link]

        PERATURAN KETAT:
        1. Tulis macam orang biasa share pemikiran genuine, BUKAN macam marketer
        2. Jangan guna frasa AI yang obvious:
        - "game-changer"
        - "dive into"
        - "unlock"
        - "leverage"
        - "dalam dunia hari ini"
        - "perlu diingatkan"
        3. Jangan mula semua ayat dengan pattern sama
        4. Grammar santai dan natural — BUKAN salah ejaan
        5. Setiap reply pendek dan senang baca
        6. Seluruh thread mesti rasa connected
        7. Jangan ulang point yang sama
        8. Hook jangan mention produk terus
        9. Link hanya dibenarkan pada reply terakhir
        10. JANGAN guna hashtag langsung
        11. Bunyi macam tengah text/member sembang, bukan blog post
        12. Natural dan imperfect dalam flow/idea — bukan dalam spelling

        VARIASI FLOW:
        {$randomFlow}

        ANTI-REPETITION:
        - Jangan ulang hook dari generation sebelum
        - Rotate antara:
        • emotional opening
        • observation
        • controversial thought
        • question-based hook
        • mini-story opening
        - Vary panjang ayat
        - Campur short punchy line dengan ayat slightly panjang
        - Boleh lowercase pada hook untuk vibe casual — tetap ejaan betul

        OUTPUT FORMAT:
        - Label setiap bahagian sebagai:
        Reply 1:
        Reply 2:
        dan seterusnya
        - Jangan tambah explanation luar thread
        PROMPT;
    }

    private function buildUserPrompt(string $category, ?Niche $niche, ?AffiliateLink $affiliate, string $ctaStyle, int $replyCount): string
    {
        $nicheName = $niche?->name ?? 'general';
        $productName = $affiliate?->product_name ?? '';
        $nicheKeywords = $niche?->keywords ? implode(', ', $niche->keywords) : '';

        $isAffiliatePost = $productName !== '';

        if ($isAffiliatePost) {
            $template = $this->categoryTemplates[$category] ?? $this->categoryTemplates['general'];
            $template = str_replace('{product}', $productName, $template);
        } else {
            $template = $this->sharingCategoryTemplates[$category] ?? $this->sharingCategoryTemplates['general'];
        }
        $template = str_replace('{niche}', $nicheName, $template);

        $prompt = "TASK: {$template}\n\n";
        $prompt .= "NICHE: {$nicheName}\n";

        if ($nicheKeywords) {
            $prompt .= "KEYWORDS TO CONSIDER: {$nicheKeywords}\n";
        }

        if ($isAffiliatePost) {
            $ctaInstruction = $this->getCTAInstruction($ctaStyle);
            $prompt .= "PRODUCT: {$productName}\n";
            $prompt .= "CTA STYLE: {$ctaInstruction}\n";
            $prompt .= "LAST REPLY: letak placeholder [link] di mana URL patut masuk (sistem akan ganti dengan URL sebenar semasa publish).\n";
        } else {
            $prompt .= "MODE: Kongsi / sharing sahaja — JANGAN letak [link], URL, atau CTA jualan.\n";
            $prompt .= "LAST REPLY: tutup dengan soalan, refleksi, atau ajak komen — bukan link atau produk.\n";
        }

        $prompt .= "\nFORMAT OUTPUT (WAJIB IKUT EXACTLY):\n";
        $prompt .= $this->buildReplyFormatSection($replyCount);
        $prompt .= "Tulis sepenuhnya dalam Bahasa Malaysia (santai, natural, ejaan betul, tanpa typo).\n";
        $prompt .= "JANGAN tambah apa-apa text lain selain format di atas. EXACTLY {$replyCount} replies.\n";
        $prompt .= "Final check: setiap perkataan mesti dibaca dengan lancar — reject output yang ada typo random.\n";

        return $prompt;
    }

    private function buildReplyFormatSection(int $replyCount): string
    {
        $hintsByCount = [
            5 => [
                1 => '[hook content]',
                2 => '[elaboration content]',
                3 => '[example/story content]',
                4 => '[insight/value content]',
                5 => '[cta/closing content]',
            ],
            6 => [
                1 => '[hook content]',
                2 => '[elaboration content]',
                3 => '[example/story content]',
                4 => '[insight/value content]',
                5 => '[buildup or emotional beat]',
                6 => '[cta/closing content]',
            ],
            7 => [
                1 => '[hook content]',
                2 => '[elaboration content]',
                3 => '[example/story content]',
                4 => '[personal observation or relatable moment]',
                5 => '[hot take or emotional reflection]',
                6 => '[insight/value content]',
                7 => '[cta/closing content]',
            ],
        ];

        $hints = $hintsByCount[$replyCount] ?? $hintsByCount[5];
        $section = '';

        for ($i = 1; $i <= $replyCount; $i++) {
            $hint = $hints[$i] ?? '[thread content]';
            $section .= "Reply {$i}:\n{$hint}\n\n";
        }

        return $section;
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
