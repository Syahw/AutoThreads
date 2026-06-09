<?php

namespace AutoThreads\Services\AI;

/**
 * ContentDiversityManager - Anti-repetition memory for generated content.
 *
 * Stores the last N hooks, opening sentences, and topic angles across all
 * generations. Before a new generation, a diversity hint is injected into
 * the system prompt so the model can avoid repeating recent patterns.
 *
 * Storage: a single JSON file in the app storage directory.
 * This is intentionally file-based (no DB dependency) so it survives
 * across requests without any schema changes.
 */
class ContentDiversityManager
{
    private const MAX_HOOKS    = 50;
    private const MAX_OPENINGS = 50;
    private const MAX_TOPICS   = 50;

    private string $storagePath;
    private array  $data;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath
            ?? $this->resolveDefaultPath();

        $this->ensureDirectoryExists();
        $this->data = $this->load();
    }

    /**
     * Build a prompt snippet listing recent hooks/topics for the model to avoid.
     * Returns an empty string when there is nothing to avoid yet.
     */
    public function buildDiversityHint(): string
    {
        $hooks  = $this->data['hooks']  ?? [];
        $topics = $this->data['topics'] ?? [];

        if (empty($hooks) && empty($topics)) {
            return '';
        }

        $hint = "ANTI-REPETITION (berdasarkan content terbaru yang dah digenerate):\n";

        if (!empty($hooks)) {
            $recent = array_slice($hooks, -6);
            $hint  .= "Elak pembukaan hook yang serupa dengan ini:\n";
            foreach ($recent as $hook) {
                $snippet = mb_substr($hook, 0, 70);
                $hint   .= "- \"{$snippet}...\"\n";
            }
        }

        if (!empty($topics)) {
            $recent = array_slice($topics, -6);
            $hint  .= "Elak ulang angle atau topik yang sama:\n";
            foreach ($recent as $topic) {
                $hint .= "- {$topic}\n";
            }
        }

        $hint .= "Jana perspektif, scene, atau sudut pandang yang segar.\n";

        return $hint;
    }

    /**
     * Record the hook and topic/niche of a successfully generated thread.
     * Call this after content is stored to DB.
     */
    public function record(string $hook, string $topic = ''): void
    {
        $this->data['hooks']  ??= [];
        $this->data['topics'] ??= [];

        if ($hook !== '') {
            $this->data['hooks'][] = mb_substr(trim($hook), 0, 120);
            if (count($this->data['hooks']) > self::MAX_HOOKS) {
                $this->data['hooks'] = array_slice($this->data['hooks'], -self::MAX_HOOKS);
            }
        }

        if ($topic !== '') {
            $this->data['topics'][] = mb_substr(trim($topic), 0, 80);
            if (count($this->data['topics']) > self::MAX_TOPICS) {
                $this->data['topics'] = array_slice($this->data['topics'], -self::MAX_TOPICS);
            }
        }

        $this->save();
    }

    /**
     * Clear diversity memory (useful for testing or resetting).
     */
    public function reset(): void
    {
        $this->data = ['hooks' => [], 'topics' => []];
        $this->save();
    }

    private function resolveDefaultPath(): string
    {
        $base = $_ENV['STORAGE_PATH'] ?? dirname(__DIR__, 3) . '/storage';
        return rtrim($base, '/\\') . '/ai/diversity.json';
    }

    private function load(): array
    {
        if (!file_exists($this->storagePath)) {
            return ['hooks' => [], 'topics' => []];
        }

        $json = file_get_contents($this->storagePath);
        $data = json_decode($json, true);

        return is_array($data) ? $data : ['hooks' => [], 'topics' => []];
    }

    private function save(): void
    {
        file_put_contents(
            $this->storagePath,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
