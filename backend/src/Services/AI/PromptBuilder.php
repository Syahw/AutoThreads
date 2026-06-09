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
        'comparison' => 'Kongsi pengalaman sebenar dengan {product}, apa yang berubah, apa yang tak expected. Jangan guna template "dua pendekatan" atau format pro/con rigid.',
        'productivity_tip' => 'Kongsi tip produktiviti yang secara natural masukkan {product} sebagai sebahagian workflow. Buat ia actionable.',
        'viral_hook' => 'Tulis hook yang buat orang stop scroll, diikuti insight bernilai yang connect dengan {product}. Utamakan curiosity gap.',
        'opinion' => 'Kongsi pendapat berani tentang ruang {niche} yang bawa kepada mention {product} sebagai solusi. Jadi authentic.',
        'list_post' => 'Buat post gaya "X benda" di mana satu item secara natural feature {product}. Pastikan setiap point ringkas.',
        'wish_i_knew' => 'Tulis post "benda aku wish aku tahu awal-awal" tentang {niche} yang include jumpa {product} sebagai satu insight.',
        'general' => 'Buat content Threads yang engaging tentang {niche} yang secara halus mention {product}. Utamakan value berbanding promosi.',
    ];

    /** Templates when no product / affiliate, sharing & engagement only */
    private array $sharingCategoryTemplates = [
        'story' => 'Tulis cerita pendek atau pengalaman peribadi tentang {niche}. Kongsi macam cerita dengan kawan, tiada jualan.',
        'product_recommendation' => 'Kongsi satu tip atau discovery tentang {niche} yang genuinely helpful. Bukan iklan produk.',
        'comparison' => 'Kongsi pengalaman peribadi dalam {niche}, apa yang kau discover, bukan essay pro/con. Elak template "dua pendekatan" atau soalan "mana satu pilihan korang".',
        'productivity_tip' => 'Kongsi tip produktiviti tentang {niche}. Buat ia actionable dan relatable.',
        'viral_hook' => 'Tulis hook yang buat orang stop scroll, diikuti insight bernilai tentang {niche}. Utamakan curiosity gap.',
        'opinion' => 'Kongsi pendapat berani tentang {niche}. Jadi authentic, ajak perbincangan, bukan jualan.',
        'list_post' => 'Buat post gaya "X benda" tentang {niche}. Pastikan setiap point ringkas dan bernilai.',
        'wish_i_knew' => 'Tulis post "benda aku wish aku tahu awal-awal" tentang {niche}. Tutup dengan refleksi atau soalan.',
        'general' => 'Buat content Threads yang engaging tentang {niche}. Utamakan value, storytelling, atau hot take, bukan promosi.',
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

        $systemPrompt = $this->buildSystemPrompt($tone, $style, $targetAudience, $replyCount, $config['diversity_hint'] ?? '');
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

    private function buildSystemPrompt(string $tone, string $style, string $audience, int $replyCount, string $diversityHint = ''): string
    {
        // Add randomness to thread flow
        $flowVariations = [
            'Thread ni boleh slow burn, ambil masa build scene sebelum sampai point.',
            'Boleh ada satu reply yang tangent random tapi somehow connected.',
            'Ending boleh anticlimactic, real life rarely wraps up neat.',
            'Middle replies boleh include specific sensory detail (bunyi, lighting, mood).',
            'Boleh admit kau still tak sure pasal sesuatu, uncertainty is human.',
            'Pacing boleh rasa macam typing while doing something else.',
            'Last reply tak perlu CTA, boleh just trail off.',
        ];

        $randomFlow = $flowVariations[array_rand($flowVariations)];

        return <<<PROMPT
        Kau seorang penulis Threads biasa, bukan influencer, bukan copywriter iklan. Semua output WAJIB dalam Bahasa Malaysia.

        Kau tulis THREAD panjang (bukan single post). Thread mesti rasa macam orang betul-betul cerita kat kawan, bukan script marketing.

        PANJANG THREAD (GENERATION INI):
        - Tulis {$replyCount} replies
        - Setiap reply WAJIB substantif, bukan one-liner kosong
        - Hook biasanya sekitar 20-45 patah perkataan, tapi ikut rasa natural
        - Middle replies biasanya 30-80 patah perkataan setiap satu (cerita, detail, konteks, perasaan)
        - Last reply biasanya 30-60 patah perkataan
        - Total thread target: 400-800+ patah perkataan keseluruhan

        SUARA & GAYA:
        - Tone: {$tone}, tapi rendahkan hype, naikkan authenticity
        - Gaya penulisan: {$style}
        - Target audience: {$audience}
        - Platform: Threads, tapi thread INI sengaja lebih panjang dan mendalam macam rant/reflection, bukan caption pendek

        BUNYI MANUSIA (PENTING):
        - Tulis macam DM atau voice note yang kau taip lepas main game / buat benda, flat, sincere, kadang boring sikit pun ok
        - WAJIB vary rhythm: ada ayat panjang meandering, ada ayat pendek biasa je. Jangan setiap ayat same energy
        - Most replies end with full stop (.), BUKAN exclamation mark
        - Max 1 tanda seru (!) untuk SELURUH thread, dan hanya kalau memang natural
        - JANGAN bunyi excited/teruja/puas hati secara paksa
        - Elak pattern robot:
          • "Seriously," / "Legit" / "Best gila" / "Memang beremosi gila"
          • "vibe unik" / "teruja" / "adrenaline" / "mantap" / "puas hati"
          • "Mana satu pilihan korang?" / "Komen bawah!" (CTA template)
          • Setiap reply tanya soalan retorik
          • List pro/con yang too neat (Reply 1 intro, Reply 2 option A, Reply 3 option B...)
        - Kalau nak tanya soalan, max 1 soalan untuk whole thread, dan biar casual, bukan poll

        BAHASA:
        - Tulis SEPENUHNYA dalam Bahasa Malaysia santai
        - EJAAN BETUL, tiada typo random
        - Guna "aku", "korang", "dah", "tak", "nak", "je", natural spoken BM
        - Jangan bahasa baku/essay ("namun", "walau bagaimanapun", "kesimpulannya", "adalah")
        - English words ok kalau natural ("literally", "actually"), jangan overuse
        - Jangan direct translate English idioms
        - JANGAN guna em dash (—) atau long dash dalam thread. Guna koma, titik, atau "tapi" je

        FLOW THREAD:
        Reply 1 (hook):
        - Mula dengan specific moment, thought, atau scene, bukan announcement "aku nak kongsi"
        - Jangan reveal semua. Tarik reader masuk slowly

        Middle replies:
        - Build cerita layer by layer, detail spesifik (nama tempat, scene, perasaan sebenar)
        - Boleh tangent sikit macam orang betul bercakap
        - Show don't tell, elakkan cliché macam "jantung berdegup"
        - Boleh admit doubt, confusion, mixed feelings, itu lebih human

        Last reply:
        - Soft landing, reflection, half-formed thought, atau low-key question
        - Kalau ada produk/link, mention natural je di sini guna [link], bukan hard sell
        - Jangan "Komen bawah!" atau engagement bait template

        PERATURAN KETAT:
        1. Genuine thought-sharing, BUKAN content creator performing excitement
        2. Jangan frasa AI: "game-changer", "dive into", "unlock", "leverage", "dalam dunia hari ini"
        3. Jangan mula consecutive replies dengan pattern sama ("Aku ingat...", "Kalau kau...", "Tapi kadang...")
        4. Grammar santai tapi ejaan betul
        5. Thread connected tapi tak rigid, boleh rasa messy macam real conversation
        6. Jangan ulang point
        7. Hook jangan mention produk
        8. Link hanya di last reply
        9. NO hashtags
        10. Prefer periods over exclamation marks
        11. NO em dash (—) in any reply

        VARIASI FLOW:
        {$randomFlow}

        ANTI-REPETITION:
        - Rotate opening styles: scene-setting, mid-thought, quiet observation, specific complaint
        - Vary sentence length aggressively
        - Some replies boleh start lowercase for casual vibe

        {$diversityHint}
        OUTPUT FORMAT:
        Reply 1:
        Reply 2:
        (dan seterusnya)
        - Tiada intro/outro di luar thread
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
            $prompt .= "MODE: Kongsi / sharing sahaja. JANGAN letak [link], URL, atau CTA jualan.\n";
            $prompt .= "LAST REPLY: tutup dengan soalan, refleksi, atau ajak komen, bukan link atau produk.\n";
        }

        $prompt .= "\nFORMAT OUTPUT (WAJIB IKUT EXACTLY):\n";
        $prompt .= $this->buildReplyFormatSection($replyCount);
        $prompt .= "Tulis sepenuhnya dalam Bahasa Melayu (santai, sincere, ejaan betul).\n";
        $prompt .= "PANJANG: middle replies biasanya 30-80 patah perkataan. Total thread sasaran 400-800+ patah perkataan.\n";
        $prompt .= "ENERGI: rendah-key, jangan paksa excitement atau tanda seru.\n";
        $prompt .= "TANDA BACA: jangan guna em dash (—) dalam thread. Guna koma atau titik je.\n";
        $prompt .= "JANGAN tambah apa-apa text lain selain format di atas. Tulis {$replyCount} replies.\n";

        return $prompt;
    }

    private function buildReplyFormatSection(int $replyCount): string
    {
        $hintsByCount = [
            5 => [
                1 => '[hook: specific scene or thought, 40-80 words]',
                2 => '[expand with detail and context]',
                3 => '[personal moment or observation]',
                4 => '[deeper reflection or tangent]',
                5 => '[soft closing: reflection or low-key question]',
            ],
            6 => [
                1 => '[hook: specific scene or thought, 40-80 words]',
                2 => '[expand with detail]',
                3 => '[story beat or specific example]',
                4 => '[mixed feelings or realization]',
                5 => '[additional context or tangent]',
                6 => '[soft closing]',
            ],
            7 => [
                1 => '[hook: specific scene or thought, 40-80 words]',
                2 => '[expand slowly]',
                3 => '[story beat with sensory detail]',
                4 => '[personal observation]',
                5 => '[honest reflection, can include doubt]',
                6 => '[connect back to main thread idea]',
                7 => '[soft closing]',
            ],
            8 => [
                1 => '[hook: scene-setting, 40-80 words]',
                2 => '[context and background]',
                3 => '[first story beat, specific detail]',
                4 => '[what happened next]',
                5 => '[how it felt, understated, not dramatic]',
                6 => '[tangent or related thought]',
                7 => '[deeper insight or realization]',
                8 => '[soft closing, no hard CTA]',
            ],
            9 => [
                1 => '[hook: mid-thought opening, 40-80 words]',
                2 => '[set the scene with specifics]',
                3 => '[story part 1]',
                4 => '[story part 2]',
                5 => '[what surprised you]',
                6 => '[honest take, can be mixed]',
                7 => '[related observation]',
                8 => '[build toward conclusion naturally]',
                9 => '[soft closing]',
            ],
            10 => [
                1 => '[hook: quiet observation or specific moment]',
                2 => '[background context, 80+ words]',
                3 => '[story beat 1]',
                4 => '[story beat 2]',
                5 => '[emotional layer, subtle, not dramatic]',
                6 => '[what you learned or still unsure about]',
                7 => '[tangent that still connects]',
                8 => '[second layer of insight]',
                9 => '[bridge to ending]',
                10 => '[soft closing, trail off naturally]',
            ],
        ];

        $hints = $hintsByCount[$replyCount] ?? $this->buildGenericHints($replyCount);
        $section = '';

        for ($i = 1; $i <= $replyCount; $i++) {
            $hint = $hints[$i] ?? '[thread content]';
            $section .= "Reply {$i}:\n{$hint}\n\n";
        }

        return $section;
    }

    private function buildGenericHints(int $replyCount): array
    {
        $hints = [];
        for ($i = 1; $i <= $replyCount; $i++) {
            if ($i === 1) {
                $hints[$i] = '[hook: specific scene or thought, 40-50 words]';
            } elseif ($i === $replyCount) {
                $hints[$i] = '[soft closing, no hard CTA]';
            } else {
                $hints[$i] = '[story beat, reflection, or detail, 60-120 words]';
            }
        }

        return $hints;
    }

    private function getCTAInstruction(string $style): string
    {
        return match ($style) {
            'soft' => 'Sebut macam side note je, "aku guna [link] ni" tanpa hard sell',
            'direct' => 'Mention [link] naturally dalam context, bukan macam iklan',
            'curiosity' => 'Tease tanpa hype, biar reader curious, bukan excited',
            'urgency' => 'Elak fake urgency. Kalau perlu mention timing, keep it low-key',
            'social_proof' => 'Elak "semua orang guna". Prefer personal experience je',
            default => 'Mention [link] macam recommendation biasa, bukan CTA template',
        };
    }

    /**
     * Augment the standard user prompt with image-analysis instructions for vision generation.
     */
    public function buildVisionUserPrompt(string $baseUserPrompt, int $imageCount, ?string $nicheName = null): string
    {
        $nicheLine = $nicheName ? "NICHE CONTEXT: {$nicheName}\n" : '';

        return <<<PROMPT
        {$nicheLine}REFERENCE IMAGE(S) ATTACHED ({$imageCount}):
        Analyze the image(s) carefully before writing the thread. Support these use cases as relevant:
        - Product identification (name, category, visible features, packaging)
        - OCR / text extraction from labels, screenshots, or documents
        - Screenshot or app UI analysis
        - Document or infographic understanding
        - Storytelling angles based on what is visually shown

        If you extract readable text from the image, output it FIRST inside this exact block (leave empty if none):
        [EXTRACTED_TEXT]
        ...text here...
        [/EXTRACTED_TEXT]

        Then generate the Threads thread using visual context + niche. Do not invent products or text not supported by the image.

        {$baseUserPrompt}
        PROMPT;
    }

    public function buildVisionSystemAddendum(): string
    {
        return <<<'PROMPT'
        You can see reference image(s). Ground the thread in what is actually visible.
        Identify products, UI elements, or document content when present.
        Extract OCR text accurately when readable; do not guess illegible text.
        PROMPT;
    }
}
