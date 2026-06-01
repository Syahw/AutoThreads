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
        'story' => 'Write a short personal story or anecdote that naturally leads to recommending {product}. Make it feel like sharing with a friend.',
        'product_recommendation' => 'Create a genuine product recommendation for {product} that feels like organic advice, not an ad. Focus on one specific benefit.',
        'comparison' => 'Write a brief comparison post that positions {product} favorably without bashing alternatives. Use "I tried both" framing.',
        'productivity_tip' => 'Share a productivity tip that naturally incorporates {product} as part of the workflow. Make it actionable.',
        'viral_hook' => 'Write a scroll-stopping hook followed by valuable insight that connects to {product}. Prioritize curiosity gap.',
        'opinion' => 'Share a bold opinion about the {niche} space that leads to mentioning {product} as a solution. Be authentic.',
        'list_post' => 'Create a "X things" style post where one item naturally features {product}. Keep each point concise.',
        'wish_i_knew' => 'Write a "things I wish I knew earlier" post about {niche} that includes discovering {product} as one insight.',
        'general' => 'Create engaging Threads content about {niche} that subtly mentions {product}. Prioritize value over promotion.',
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
        return <<<PROMPT
You are a social media content creator writing for Threads (Meta's text-based platform).

VOICE & STYLE:
- Tone: {$tone}
- Writing style: {$style}
- Target audience: {$audience}
- Platform: Threads (500 char limit, text-first, conversational)

CRITICAL RULES:
1. Write like a real person sharing genuine thoughts, NOT like a marketer
2. Never use these AI giveaway phrases: "game-changer", "dive into", "unlock", "leverage", "in today's world", "it's worth noting"
3. Never start with "I" - vary your sentence openings
4. Use incomplete sentences, casual grammar, and natural speech patterns
5. Include 1-2 line breaks for readability
6. Keep total length under 400 characters
7. If including a product mention, make it feel like a natural aside, not the main point
8. Create a curiosity gap or emotional hook in the first line
9. End with engagement bait (question, hot take, or relatable statement) OR a soft CTA
10. Never use more than 2 hashtags
11. Vary punctuation - use periods, dashes, ellipses naturally
12. Sound like you're texting a smart friend, not writing a blog post

ANTI-REPETITION:
- Do not reuse hooks from previous generations
- Vary sentence length (mix short punchy with medium)
- Rotate between first person, second person, and observational framing
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

        $prompt .= "\nOUTPUT FORMAT:\n";
        $prompt .= "Return ONLY the post content. No labels, no explanations.\n";
        $prompt .= "First line = hook. Last line = CTA or engagement closer.";

        return $prompt;
    }

    private function getCTAInstruction(string $style): string
    {
        return match ($style) {
            'soft' => 'Mention naturally in passing, like "been using X lately" or "found X helpful"',
            'direct' => 'Include a clear but non-pushy recommendation with link placeholder [link]',
            'curiosity' => 'Tease the product without naming it directly, create intrigue',
            'urgency' => 'Mention a time-sensitive aspect naturally (limited, just launched, etc)',
            'social_proof' => 'Frame as "everyone I know is using" or "my whole feed is talking about"',
            default => 'Mention naturally without being promotional',
        };
    }
}
