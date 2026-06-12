<?php

namespace AutoThreads\Services\AI;

/**
 * Hook-builder option IDs and Malaysian social-media copywriter instructions.
 * Mirrors frontend hookBuilder.js.
 */
class HookBuilderCatalog
{
    /** @var array<string, array{multi: bool, options: list<string>}> */
    private const GROUPS = [
        'hook_style' => [
            'multi' => false,
            'options' => [
                'storytelling', 'fomo', 'urgency', 'problem_solution', 'self_thought',
            ],
        ],
    ];

    /** @return array<string, array{multi: bool, options: list<string>}> */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function isValidStyle(string $styleId): bool
    {
        return in_array($styleId, self::GROUPS['hook_style']['options'], true);
    }

    public static function selectionPrompt(): string
    {
        $lines = ["HOOK BUILDER OPTIONS (use exact IDs only):\n"];

        foreach (self::GROUPS as $groupId => $meta) {
            $pick = $meta['multi'] ? 'pick 1-3' : 'pick exactly 1';
            $lines[] = "- {$groupId} ({$pick}): " . implode(', ', $meta['options']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{topic: string, product_summary: string, selections: array<string, list<string>>}
     */
    public static function sanitizeSuggestion(array $raw): array
    {
        $topic = trim((string) ($raw['topic'] ?? ''));
        $productSummary = trim((string) ($raw['product_summary'] ?? ''));
        $rawSelections = is_array($raw['selections'] ?? null) ? $raw['selections'] : [];

        $selections = [];

        foreach (self::GROUPS as $groupId => $meta) {
            $valid = $meta['options'];
            $incoming = $rawSelections[$groupId] ?? [];
            if (!is_array($incoming)) {
                $incoming = [$incoming];
            }

            $picked = [];
            foreach ($incoming as $id) {
                $id = trim((string) $id);
                if ($id !== '' && in_array($id, $valid, true) && !in_array($id, $picked, true)) {
                    $picked[] = $id;
                }
            }

            if (!$meta['multi'] && count($picked) > 1) {
                $picked = [array_values($picked)[0]];
            }

            if ($picked !== []) {
                $selections[$groupId] = $picked;
            }
        }

        return [
            'topic' => $topic,
            'product_summary' => $productSummary,
            'selections' => $selections,
        ];
    }

    /**
     * System-prompt addendum for Reply 1 when a hook style is active.
     */
    public static function systemAddendum(string $styleId, string $language = 'bm'): string
    {
        $styleId = self::normalizeStyleId($styleId);
        if ($styleId === null) {
            return '';
        }

        $lang = $language === 'en' ? 'en' : 'bm';
        $style = self::styleDefinition($styleId, $lang);
        $global = self::globalHookRules($lang);

        return <<<PROMPT
        HOOK STYLE ACTIVE (Reply 1 ONLY — WAJIB IKUT):
        {$global}

        GAYA: {$style['label']}
        TUJUAN: {$style['purpose']}
        FORMULA: {$style['formula']}

        CONTOH BUNYI (inspirasi sahaja — jangan copy verbatim, vary structure):
        {$style['examples']}

        PERATURAN HOOK INI:
        - Reply 1 MESTI bunyi macam gaya ni, bukan generic scene-setting
        - Jangan sebut nama kategori hook
        - Jangan sebut produk dalam Reply 1
        - Max 15 patah perkataan untuk Reply 1
        - Ayat pendek, punchy, macam UGC Malaysia sebenar
        PROMPT;
    }

    /**
     * User-prompt hook instruction block (appended to generation request).
     */
    public static function buildInstruction(
        string $styleId,
        string $topic = '',
        string $product = '',
        string $productContext = '',
        string $language = 'bm',
        bool $hooksOnly = false,
    ): string {
        $styleId = self::normalizeStyleId($styleId);
        if ($styleId === null) {
            return '';
        }

        $lang = $language === 'en' ? 'en' : 'bm';
        $style = self::styleDefinition($styleId, $lang);
        $global = self::globalHookRules($lang);
        $lines = [];

        $lines[] = $lang === 'en'
            ? 'HOOK INSTRUCTION (Reply 1):'
            : 'HOOK INSTRUCTION (Reply 1):';
        $lines[] = $global;
        $lines[] = '';
        $lines[] = ($lang === 'en' ? 'HOOK STYLE' : 'GAYA HOOK') . ': ' . $style['label'];
        $lines[] = ($lang === 'en' ? 'PURPOSE' : 'TUJUAN') . ': ' . $style['purpose'];
        $lines[] = ($lang === 'en' ? 'FORMULA' : 'FORMULA') . ': ' . $style['formula'];
        $lines[] = ($lang === 'en' ? 'EXAMPLES (inspiration only — vary, do not copy)' : 'CONTOH (inspirasi sahaja — vary, jangan copy)') . ':';
        foreach ($style['example_list'] as $example) {
            $lines[] = "- {$example}";
        }

        $context = trim($productContext);
        if ($context !== '') {
            $lines[] = '';
            $lines[] = 'PRODUCT CONTEXT: ' . $context;
        }

        if (trim($topic) !== '') {
            $lines[] = '';
            $lines[] = ($lang === 'en' ? 'TOPIC' : 'TOPIK') . ': ' . trim($topic);
        }

        if (trim($product) !== '') {
            $lines[] = ($lang === 'en' ? 'PRODUCT' : 'PRODUK') . ': ' . trim($product);
        }

        if (!$hooksOnly) {
            $lines[] = '';
            $lines[] = $lang === 'en'
                ? 'OUTPUT: Write Reply 1 using this hook style only. Middle/last replies follow normal thread rules. Do not mention the product in Reply 1.'
                : 'OUTPUT: Tulis Reply 1 ikut gaya hook ni. Reply tengah/akhir ikut peraturan thread biasa. Jangan sebut produk dalam Reply 1.';
        }

        return implode("\n", $lines);
    }

    private static function normalizeStyleId(string $styleId): ?string
    {
        $styleId = trim($styleId);

        return self::isValidStyle($styleId) ? $styleId : null;
    }

  /** @return array{label: string, purpose: string, formula: string, examples: string, example_list: list<string>} */
    private static function styleDefinition(string $styleId, string $lang): array
    {
        $definitions = self::definitions();

        return $definitions[$styleId][$lang];
    }

    private static function globalHookRules(string $lang): string
    {
        if ($lang === 'en') {
            return implode("\n", [
                'Write like a real Malaysian on social media — natural, conversational, relatable.',
                'Avoid robotic, generic, or overly salesy language.',
                'Avoid excessive emojis. Short, punchy sentences. Max 15 words for Reply 1.',
                'Create curiosity. Feel like personal experience, observation, problem, or recommendation.',
                'Do NOT start every hook with the same structure. Vary sentence patterns.',
                'Avoid ALL CAPS. Avoid "anda" — prefer "kau", "korang", or natural spoken BM.',
                'Sound like authentic UGC, not ad copy. No fake clickbait.',
            ]);
        }

        return implode("\n", [
            'Bunyi macam orang Malaysia sebenar di social media — natural, conversational, relatable.',
            'Elak bahasa robot, generic, atau terlalu jualan.',
            'Elak emoji berlebihan. Ayat pendek, punchy. Max 15 patah perkataan untuk Reply 1.',
            'Cipta curiosity. Rasa macam pengalaman peribadi, pemerhatian, masalah, atau cadangan.',
            'JANGAN mula setiap hook dengan struktur sama. Vary pattern ayat secara natural.',
            'Elak ALL CAPS. Elak "anda" — prefer "kau", "korang", atau BM santai.',
            'Bunyi macam UGC authentic, bukan iklan. Jangan clickbait palsu.',
        ]);
    }

    /** @return array<string, array<string, array{label: string, purpose: string, formula: string, examples: string, example_list: list<string>}>> */
    private static function definitions(): array
    {
        return [
            'storytelling' => [
                'bm' => [
                    'label' => 'Storytelling',
                    'purpose' => 'Mula dengan pengalaman peribadi, journey, atau transformation.',
                    'formula' => 'Situasi lalu → Turning point → Curiosity',
                    'example_list' => [
                        'Aku ingat lagi kali pertama cuba benda ni...',
                        'Mula-mula aku ingat benda ni cuma gimik...',
                        'Ada satu masa tu aku hampir give up...',
                        'Tak sangka benda simple macam ni boleh ubah rutin aku.',
                    ],
                    'examples' => '',
                ],
                'en' => [
                    'label' => 'Storytelling',
                    'purpose' => 'Start with a personal experience, journey, or transformation.',
                    'formula' => 'Past situation → Turning point → Curiosity',
                    'example_list' => [
                        'I still remember the first time I tried this...',
                        'At first I thought this was just hype...',
                        'There was a point I almost gave up...',
                        'Didn\'t expect something this simple could change my routine.',
                    ],
                    'examples' => '',
                ],
            ],
            'fomo' => [
                'bm' => [
                    'label' => 'FOMO',
                    'purpose' => 'Buat orang rasa mungkin terlepas peluang.',
                    'formula' => 'Orang lain dah benefit → Reader mungkin miss peluang',
                    'example_list' => [
                        'Ramai orang dah guna benda ni, kau masih belum cuba?',
                        'Baru faham kenapa produk ni asyik sold out.',
                        'Kalau kau nampak ni sekarang, jangan tunggu lama.',
                    ],
                    'examples' => '',
                ],
                'en' => [
                    'label' => 'FOMO',
                    'purpose' => 'Make people feel they might be missing out.',
                    'formula' => 'Others are benefiting → Reader may miss the opportunity',
                    'example_list' => [
                        'A lot of people are already using this — have you tried it yet?',
                        'Finally get why this keeps selling out.',
                        'If you\'re seeing this now, don\'t wait too long.',
                    ],
                    'examples' => '',
                ],
            ],
            'urgency' => [
                'bm' => [
                    'label' => 'Urgency',
                    'purpose' => 'Galakkan tindakan segera tanpa hype palsu.',
                    'formula' => 'Masa terhad → Potential loss → Action',
                    'example_list' => [
                        'Kalau nak beli, minggu ni mungkin masa paling sesuai.',
                        'Promo ni tinggal beberapa hari je lagi.',
                        'Jangan tunggu harga naik baru nak menyesal.',
                        'Kalau korang tak beli sekarang rugi weh sebab harga tengah murah.',
                    ],
                    'examples' => '',
                ],
                'en' => [
                    'label' => 'Urgency',
                    'purpose' => 'Encourage immediate action without fake hype.',
                    'formula' => 'Limited time → Potential loss → Action',
                    'example_list' => [
                        'If you\'re buying, this week might be the sweet spot.',
                        'This promo only has a few days left.',
                        'Don\'t wait till the price goes up to regret it.',
                        'If you don\'t grab this now you\'re missing out while it\'s cheap.',
                    ],
                    'examples' => '',
                ],
            ],
            'problem_solution' => [
                'bm' => [
                    'label' => 'Problem Solution',
                    'purpose' => 'Highlight pain point dan hint penyelesaian.',
                    'formula' => 'Masalah → Frustration → Solution teaser',
                    'example_list' => [
                        'Penat dah cuba macam-macam tapi hasil tetap sama?',
                        'Kalau kau selalu hadap masalah ni, cuba tengok ni.',
                        'Rupanya punca masalah ni bukan macam yang aku sangka.',
                    ],
                    'examples' => '',
                ],
                'en' => [
                    'label' => 'Problem Solution',
                    'purpose' => 'Highlight a pain point and hint at a solution.',
                    'formula' => 'Problem → Frustration → Solution teaser',
                    'example_list' => [
                        'Tired of trying everything but getting the same results?',
                        'If you keep running into this problem, look at this.',
                        'Turns out the root cause wasn\'t what I thought.',
                    ],
                    'examples' => '',
                ],
            ],
            'self_thought' => [
                'bm' => [
                    'label' => 'Self thought / sharing',
                    'purpose' => 'Kongsi pendapat peribadi atau pemerhatian.',
                    'formula' => 'Personal thought → Observation → Insight',
                    'example_list' => [
                        'Aku rasa ramai orang sebenarnya tak sedar benda ni.',
                        'Selepas beberapa minggu guna, ini pendapat aku.',
                        'Aku perasan semakin ramai orang mula buat macam ni.',
                    ],
                    'examples' => '',
                ],
                'en' => [
                    'label' => 'Self thought / sharing',
                    'purpose' => 'Share personal opinions or observations.',
                    'formula' => 'Personal thought → Observation → Insight',
                    'example_list' => [
                        'I think a lot of people don\'t actually realize this.',
                        'After a few weeks of using it, here\'s my take.',
                        'I\'ve noticed more people starting to do this.',
                    ],
                    'examples' => '',
                ],
            ],
        ];
    }
}
