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

    private array $categoryTemplatesEn = [
        'story' => 'Write a short personal story or experience that naturally leads to recommending {product}. Make it feel like telling a friend.',
        'product_recommendation' => 'Write a genuine product recommendation for {product}, like organic advice not an ad. Focus on one specific benefit.',
        'comparison' => 'Share a real experience with {product}, what changed, what was unexpected. Avoid rigid pro/con templates.',
        'productivity_tip' => 'Share a productivity tip that naturally includes {product} as part of the workflow. Make it actionable.',
        'viral_hook' => 'Write a scroll-stopping hook followed by valuable insight connected to {product}. Prioritize curiosity gap.',
        'opinion' => 'Share a bold opinion about {niche} that naturally leads to mentioning {product} as a solution. Stay authentic.',
        'list_post' => 'Write an "X things" style post where one item naturally features {product}. Keep each point concise.',
        'wish_i_knew' => 'Write a "things I wish I knew earlier" post about {niche} that includes discovering {product} as one insight.',
        'general' => 'Write engaging Threads content about {niche} that subtly mentions {product}. Prioritize value over promotion.',
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

    private array $sharingCategoryTemplatesEn = [
        'story' => 'Write a short personal story or experience about {niche}. Share it like telling a friend, no selling.',
        'product_recommendation' => 'Share one genuinely helpful tip or discovery about {niche}. Not a product ad.',
        'comparison' => 'Share a personal experience in {niche}, what you discovered — not a rigid pro/con essay.',
        'productivity_tip' => 'Share a productivity tip about {niche}. Make it actionable and relatable.',
        'viral_hook' => 'Write a scroll-stopping hook followed by valuable insight about {niche}. Prioritize curiosity gap.',
        'opinion' => 'Share a bold opinion about {niche}. Be authentic, invite discussion, no selling.',
        'list_post' => 'Write an "X things" style post about {niche}. Keep each point concise and valuable.',
        'wish_i_knew' => 'Write a "things I wish I knew earlier" post about {niche}. End with reflection or a question.',
        'general' => 'Write engaging Threads content about {niche}. Prioritize value, storytelling, or hot takes, not promotion.',
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
        $language = $this->normalizeLanguage($config['language'] ?? 'bm');

        // Select tone (use requested or rotate)
        $tone = $requestedTone ?? $this->getRotatedTone();
        $style = $this->getRotatedStyle();

        $threadLength = $config['thread_length'] ?? null;
        $replyCount = $this->getReplyCountForLength($threadLength);

        $systemPrompt = $this->buildSystemPrompt($tone, $style, $targetAudience, $replyCount, $config['diversity_hint'] ?? '', $language, $threadLength);
        $userPrompt = $this->buildUserPrompt($category, $niche, $affiliate, $ctaStyle, $replyCount, $language);

        $productContext = trim((string) ($config['product_context'] ?? ''));
        if ($productContext !== '') {
            $userPrompt .= "\n\nPRODUCT CONTEXT: {$productContext}";
        }

        $hookStyle = trim((string) ($config['hook_style'] ?? ''));
        $hookInstruction = trim((string) ($config['hook_instruction'] ?? ''));

        if ($hookStyle !== '' && HookBuilderCatalog::isValidStyle($hookStyle)) {
            $productName = $affiliate?->product_name ?? '';
            $hookTopic = trim((string) ($config['hook_topic'] ?? ''));
            $hookInstruction = HookBuilderCatalog::buildInstruction(
                $hookStyle,
                $hookTopic,
                $productName,
                $productContext,
                $language,
            );
            $systemPrompt .= "\n\n" . HookBuilderCatalog::systemAddendum($hookStyle, $language);
        }

        if ($hookInstruction !== '') {
            $userPrompt .= "\n\n" . $hookInstruction;
        }

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'hook_style' => $hookStyle !== '' ? $hookStyle : null,
            'tone_used' => $tone,
            'style_used' => $style,
            'reply_count' => $replyCount,
            'language' => $language,
        ];
    }

    /**
     * Build prompts for hooks-only generation (no thread replies).
     *
     * @return array{system: string, user: string, hook_style: ?string, hook_count: int, language: string}
     */
    public function buildHooksOnly(array $config): array
    {
        $affiliate = $config['affiliate'] ?? null;
        $language = $this->normalizeLanguage($config['language'] ?? 'bm');
        $hookCount = max(1, min(20, (int) ($config['hook_count'] ?? 5)));
        $hookStyle = trim((string) ($config['hook_style'] ?? ''));
        $productContext = trim((string) ($config['product_context'] ?? ''));
        $productName = $affiliate?->product_name ?? '';
        $hookTopic = trim((string) ($config['hook_topic'] ?? ''));

        if ($hookStyle === '' || !HookBuilderCatalog::isValidStyle($hookStyle)) {
            throw new \InvalidArgumentException('A valid hook_style is required for hooks-only generation');
        }

        $systemPrompt = $this->buildHooksOnlySystemPrompt($language, $hookCount);
        $systemPrompt .= "\n\n" . HookBuilderCatalog::systemAddendum($hookStyle, $language);

        $userPrompt = HookBuilderCatalog::buildInstruction(
            $hookStyle,
            $hookTopic,
            $productName,
            $productContext,
            $language,
            true,
        );

        $userPrompt .= "\n\n" . $this->buildHooksOnlyOutputSection($hookCount, $language);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'hook_style' => $hookStyle,
            'hook_count' => $hookCount,
            'language' => $language,
        ];
    }

    private function buildHooksOnlySystemPrompt(string $language, int $hookCount): string
    {
        if ($language === 'en') {
            return <<<PROMPT
            You are an expert Malaysian social media copywriter.

            Your task is to generate HIGH-QUALITY hooks in natural, casual English suited for Malaysian social audiences (TikTok, Threads, Facebook, Instagram).

            Requirements:
            - Sound like a real Malaysian person online.
            - Natural, conversational, and relatable.
            - Avoid robotic, generic, or overly salesy language.
            - Avoid excessive emojis.
            - Use short, punchy sentences.
            - Create curiosity and encourage people to keep reading.
            - Hooks should feel like personal experiences, observations, problems, or recommendations.
            - Do NOT start every hook with the same structure.
            - Vary sentence patterns naturally.
            - Maximum 15 words per hook.
            - Never mention product names in hooks.
            - Never mention the hook category name.
            - Avoid ALL CAPS and fake clickbait.
            - Prefer "you" in a casual friend tone.

            Generate exactly {$hookCount} unique hooks.
            Output ONLY the hooks in the required format. No intro or outro.
            PROMPT;
        }

        return <<<PROMPT
        Kau seorang penulis copy social media Malaysia yang pakar.

        Tugas kau: hasilkan hook BERKUALITI tinggi dalam Bahasa Melayu untuk kandungan social media (TikTok, Threads, Facebook, Instagram).

        Keperluan:
        - Bunyi macam orang Malaysia sebenar.
        - Natural, conversational, relatable.
        - Elak bahasa robot, generic, atau terlalu jualan.
        - Elak emoji berlebihan.
        - Guna ayat pendek, punchy.
        - Cipta curiosity dan galakkan orang terus baca.
        - Hook rasa macam pengalaman peribadi, pemerhatian, masalah, atau cadangan.
        - JANGAN mula setiap hook dengan struktur sama.
        - Vary pattern ayat secara natural.
        - Maximum 15 patah perkataan setiap hook.
        - Jangan sebut nama produk dalam hook.
        - Jangan sebut nama kategori hook.
        - Elak ALL CAPS dan clickbait palsu.
        - Elak "anda" — prefer "kau", "korang", atau BM santai.

        Hasilkan tepat {$hookCount} hook unik.
        Output HANYA hook dalam format yang diminta. Tiada intro/outro.
        PROMPT;
    }

    private function buildHooksOnlyOutputSection(int $hookCount, string $language): string
    {
        $lines = [];
        if ($language === 'en') {
            $lines[] = 'OUTPUT FORMAT (required):';
            for ($i = 1; $i <= $hookCount; $i++) {
                $lines[] = "Hook {$i}:";
                $lines[] = '[one unique hook, max 15 words]';
                $lines[] = '';
            }
            $lines[] = "Write exactly {$hookCount} hooks. Each must be distinct in structure and angle.";
            $lines[] = 'Do not write thread replies or anything outside the hooks.';

            return implode("\n", $lines);
        }

        $lines[] = 'FORMAT OUTPUT (wajib):';
        for ($i = 1; $i <= $hookCount; $i++) {
            $lines[] = "Hook {$i}:";
            $lines[] = '[satu hook unik, max 15 patah perkataan]';
            $lines[] = '';
        }
        $lines[] = "Tulis tepat {$hookCount} hook. Setiap satu mesti berbeza struktur dan sudut.";
        $lines[] = 'Jangan tulis reply thread atau apa-apa selain hook.';

        return implode("\n", $lines);
    }

    private function normalizeLanguage(?string $language): string
    {
        $language = strtolower(trim((string) $language));

        return $language === 'en' ? 'en' : 'bm';
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

    private function getReplyCountForLength(?string $length): int
    {
        return match (strtolower(trim((string) $length))) {
            'short' => random_int(4, 5),
            'long' => random_int(6, 7),
            default => random_int(5, 6),
        };
    }

    private function buildSystemPrompt(string $tone, string $style, string $audience, int $replyCount, string $diversityHint = '', string $language = 'bm', ?string $threadLength = null): string
    {
        if ($language === 'en') {
            return $this->buildSystemPromptEn($tone, $style, $audience, $replyCount, $diversityHint, $threadLength);
        }

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
        $lengthHint = $this->threadLengthHint($threadLength, 'bm');

        return <<<PROMPT
        Kau seorang penulis Threads biasa, bukan influencer, bukan copywriter iklan. Semua output WAJIB dalam Bahasa Malaysia.

        Kau tulis THREAD panjang (bukan single post). Thread mesti rasa macam orang betul-betul cerita kat kawan, bukan script marketing.

        PANJANG THREAD (GENERATION INI):
        - Tulis {$replyCount} replies
        - {$lengthHint}
        - Setiap reply WAJIB substantif, bukan one-liner kosong
        - Hook biasanya 15 patah perkataan (max) kalau gaya hook dipilih; kalau tidak, 20-45 patah perkataan
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
        - Kalau ada HOOK STYLE ACTIVE di bawah, Reply 1 WAJIB ikut gaya tu (max 15 patah perkataan, punchy)

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

    private function threadLengthHint(?string $length, string $language): string
    {
        $length = strtolower(trim((string) $length));

        if ($language === 'en') {
            return match ($length) {
                'short' => 'Keep pacing tight — shorter scenes, get to the point faster.',
                'long' => 'Allow slow burn — more scene-setting and gradual reveals across replies.',
                default => 'Balanced pacing — mix scene-setting with steady progression.',
            };
        }

        return match ($length) {
            'short' => 'Pacing ketat — scene pendek, cepat sampai point.',
            'long' => 'Slow burn — lebih scene-setting dan reveal beransur.',
            default => 'Pacing seimbang — campur scene-setting dengan progression steady.',
        };
    }

    private function buildSystemPromptEn(string $tone, string $style, string $audience, int $replyCount, string $diversityHint = '', ?string $threadLength = null): string
    {
        $flowVariations = [
            'This thread can slow burn — take time building the scene before the point.',
            'One reply can tangent randomly but still feel connected.',
            'The ending can be anticlimactic; real life rarely wraps up neat.',
            'Middle replies can include specific sensory detail (sound, lighting, mood).',
            'You can admit you are still unsure about something — uncertainty is human.',
            'Pacing can feel like typing while doing something else.',
            'The last reply does not need a CTA — it can just trail off.',
        ];

        $randomFlow = $flowVariations[array_rand($flowVariations)];
        $lengthHint = $this->threadLengthHint($threadLength, 'en');

        return <<<PROMPT
        You are a regular Threads writer, not an influencer or ad copywriter. All output MUST be in English.

        You write a LONG thread (not a single post). The thread must feel like a real person talking to a friend, not a marketing script.

        THREAD LENGTH (THIS GENERATION):
        - Write {$replyCount} replies
        - {$lengthHint}
        - Each reply MUST be substantive, not empty one-liners
        - Hook is usually max 15 words when a hook style is selected; otherwise 20-45 words
        - Middle replies are usually 30-80 words each (story, detail, context, feeling)
        - Last reply is usually 30-60 words
        - Total thread target: 400-800+ words overall

        VOICE & STYLE:
        - Tone: {$tone}, but lower the hype, raise authenticity
        - Writing style: {$style}
        - Target audience: {$audience}
        - Platform: Threads, but this thread is intentionally longer and deeper like a rant/reflection, not a short caption

        SOUND HUMAN (IMPORTANT):
        - Write like a DM or voice note typed after gaming or doing something — flat, sincere, slightly boring is ok
        - MUST vary rhythm: long meandering sentences and short plain ones. Do not make every sentence the same energy
        - Most replies end with a full stop (.), NOT an exclamation mark
        - Max 1 exclamation mark (!) for the ENTIRE thread, and only if truly natural
        - Do NOT sound forcibly excited or hyped
        - Avoid robotic patterns:
          • "Seriously," / "Legit" / "Best ever" / "So emotional"
          • "unique vibe" / "hyped" / "adrenaline" / "amazing" / "so satisfied"
          • "Which one do you pick?" / "Comment below!" (CTA templates)
          • Every reply asking a rhetorical question
          • Too-neat pro/con lists (Reply 1 intro, Reply 2 option A, Reply 3 option B...)
        - If asking a question, max 1 for the whole thread, and keep it casual, not a poll

        LANGUAGE:
        - Write ENTIRELY in natural, casual English
        - Correct spelling, no random typos
        - Use contractions and spoken phrasing where natural
        - Avoid essay/formal language ("furthermore", "in conclusion", "it is worth noting")
        - Do NOT use em dash (—) or long dashes in the thread. Use commas, periods, or "but"

        THREAD FLOW:
        Reply 1 (hook):
        - Start with a specific moment, thought, or scene — not "let me share"
        - Do not reveal everything. Pull the reader in slowly
        - If HOOK STYLE ACTIVE appears below, Reply 1 MUST follow that style (max 15 words, punchy)

        Middle replies:
        - Build the story layer by layer with specific detail (place, scene, real feelings)
        - You can tangent a little like a real person talking
        - Show don't tell, avoid clichés like "heart racing"
        - You can admit doubt, confusion, mixed feelings — that is more human

        Last reply:
        - Soft landing, reflection, half-formed thought, or low-key question
        - If there is a product/link, mention it naturally here using [link], not hard sell
        - No "Comment below!" or engagement-bait templates

        STRICT RULES:
        1. Genuine thought-sharing, NOT a content creator performing excitement
        2. No AI phrases: "game-changer", "dive into", "unlock", "leverage", "in today's world"
        3. Do not start consecutive replies with the same pattern ("I remember...", "If you...", "But sometimes...")
        4. Casual grammar but correct spelling
        5. Thread is connected but not rigid — can feel messy like real conversation
        6. Do not repeat points
        7. Hook must not mention the product
        8. Link only in the last reply
        9. NO hashtags
        10. Prefer periods over exclamation marks
        11. NO em dash (—) in any reply

        FLOW VARIATION:
        {$randomFlow}

        ANTI-REPETITION:
        - Rotate opening styles: scene-setting, mid-thought, quiet observation, specific complaint
        - Vary sentence length aggressively
        - Some replies may start lowercase for a casual vibe

        {$diversityHint}
        OUTPUT FORMAT:
        Reply 1:
        Reply 2:
        (and so on)
        - No intro/outro outside the thread
        PROMPT;
    }

    private function buildUserPrompt(string $category, ?Niche $niche, ?AffiliateLink $affiliate, string $ctaStyle, int $replyCount, string $language = 'bm'): string
    {
        $nicheName = $niche?->name ?? 'general';
        $productName = $affiliate?->product_name ?? '';
        $nicheKeywords = $niche?->keywords ? implode(', ', $niche->keywords) : '';

        $isAffiliatePost = $productName !== '';
        $isEnglish = $language === 'en';

        if ($isAffiliatePost) {
            $templates = $isEnglish ? $this->categoryTemplatesEn : $this->categoryTemplates;
            $template = $templates[$category] ?? $templates['general'];
            $template = str_replace('{product}', $productName, $template);
        } else {
            $templates = $isEnglish ? $this->sharingCategoryTemplatesEn : $this->sharingCategoryTemplates;
            $template = $templates[$category] ?? $templates['general'];
        }
        $template = str_replace('{niche}', $nicheName, $template);

        $prompt = "TASK: {$template}\n\n";
        $prompt .= "NICHE: {$nicheName}\n";

        if ($nicheKeywords) {
            $prompt .= "KEYWORDS TO CONSIDER: {$nicheKeywords}\n";
        }

        if ($isAffiliatePost) {
            $ctaInstruction = $this->getCTAInstruction($ctaStyle, $language);
            $prompt .= "PRODUCT: {$productName}\n";
            $prompt .= "CTA STYLE: {$ctaInstruction}\n";
            if ($isEnglish) {
                $prompt .= "LAST REPLY: place the [link] placeholder where the URL should go (the system replaces it with the real URL on publish).\n";
            } else {
                $prompt .= "LAST REPLY: letak placeholder [link] di mana URL patut masuk (sistem akan ganti dengan URL sebenar semasa publish).\n";
            }
        } else {
            if ($isEnglish) {
                $prompt .= "MODE: Sharing only. Do NOT include [link], URLs, or sales CTAs.\n";
                $prompt .= "LAST REPLY: end with a question, reflection, or invite to comment — not a link or product.\n";
            } else {
                $prompt .= "MODE: Kongsi / sharing sahaja. JANGAN letak [link], URL, atau CTA jualan.\n";
                $prompt .= "LAST REPLY: tutup dengan soalan, refleksi, atau ajak komen, bukan link atau produk.\n";
            }
        }

        $prompt .= "\nFORMAT OUTPUT (WAJIB IKUT EXACTLY):\n";
        $prompt .= $this->buildReplyFormatSection($replyCount);
        if ($isEnglish) {
            $prompt .= "Write entirely in natural, casual English.\n";
            $prompt .= "LENGTH: middle replies are usually 30-80 words. Total thread target 400-800+ words.\n";
            $prompt .= "ENERGY: low-key, do not force excitement or exclamation marks.\n";
            $prompt .= "PUNCTUATION: do not use em dash (—) in the thread. Use commas or periods.\n";
            $prompt .= "Do not add any text outside the format above. Write {$replyCount} replies.\n";
        } else {
            $prompt .= "Tulis sepenuhnya dalam Bahasa Melayu (santai, sincere, ejaan betul).\n";
            $prompt .= "PANJANG: middle replies biasanya 30-80 patah perkataan. Total thread sasaran 400-800+ patah perkataan.\n";
            $prompt .= "ENERGI: rendah-key, jangan paksa excitement atau tanda seru.\n";
            $prompt .= "TANDA BACA: jangan guna em dash (—) dalam thread. Guna koma atau titik je.\n";
            $prompt .= "JANGAN tambah apa-apa text lain selain format di atas. Tulis {$replyCount} replies.\n";
        }

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

    private function getCTAInstruction(string $style, string $language = 'bm'): string
    {
        if ($language === 'en') {
            return match ($style) {
                'soft' => 'Mention it like a side note, "I use [link] for this" without hard selling',
                'direct' => 'Mention [link] naturally in context, not like an ad',
                'curiosity' => 'Tease without hype — make readers curious, not excited',
                'urgency' => 'Avoid fake urgency. If mentioning timing, keep it low-key',
                'social_proof' => 'Avoid "everyone uses this". Prefer personal experience only',
                default => 'Mention [link] like a normal recommendation, not a CTA template',
            };
        }

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
