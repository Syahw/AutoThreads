<?php

namespace AutoThreads\Services\Admin;

class SystemSettings
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(__DIR__, 3) . '/storage/app/system_settings.json';
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if (!is_readable($this->path)) {
            return $this->defaults();
        }

        $raw = file_get_contents($this->path);
        $data = json_decode($raw ?: '{}', true);

        return array_replace_recursive($this->defaults(), is_array($data) ? $data : []);
    }

    /** @param array<string, mixed> $updates */
    public function update(array $updates): array
    {
        $merged = $this->all();

        foreach ($updates as $key => $value) {
            if (is_array($value) && $this->isIndexedList($value)) {
                // Replace lists entirely (e.g. announcements) — recursive merge would keep deleted items.
                $merged[$key] = array_values($value);
                continue;
            }

            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]) && !$this->isIndexedList($merged[$key])) {
                $merged[$key] = array_replace_recursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        file_put_contents($this->path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $merged;
    }

    /** @param array<mixed> $array */
    private function isIndexedList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_is_list($array);
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'general' => [
                'site_name' => $_ENV['APP_NAME'] ?? 'AutoThreads',
                'timezone' => $_ENV['SCHEDULER_TIMEZONE'] ?? 'UTC',
            ],
            'scheduler' => [
                'earliest_hour' => (int) ($_ENV['SCHEDULER_EARLIEST_HOUR'] ?? 0),
                'latest_hour' => (int) ($_ENV['SCHEDULER_LATEST_HOUR'] ?? 23),
                'max_retries' => 3,
                'worker_frequency_minutes' => 1,
            ],
            'ai' => [
                'default_model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini',
                'max_tokens' => (int) ($_ENV['OPENAI_MAX_TOKENS'] ?? 2000),
                'daily_user_limit' => 50,
            ],
            'plans' => [
                'free' => ['posts_per_month' => 10],
                'starter' => ['posts_per_month' => 50],
                'pro' => ['posts_per_month' => 500],
                'enterprise' => ['posts_per_month' => 5000],
            ],
            'features' => [
                'ai_generation' => true,
                'bulk_scheduling' => true,
                'image_upload' => true,
                'analytics' => true,
                'affiliate_templates' => true,
            ],
            'announcements' => [],
        ];
    }
}
