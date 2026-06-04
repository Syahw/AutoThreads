<?php

namespace AutoThreads\Services\Scheduler;

/**
 * Append-only log for Windows Task Scheduler / cron visibility.
 */
class CronLogger
{
    private string $logPath;

    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?? dirname(__DIR__, 3) . '/storage/logs/cron-publish.log';
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function line(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $tz = date_default_timezone_get();
        $line = "[{$timestamp} {$tz}] {$message}" . PHP_EOL;
        $this->append($line);

        if (PHP_SAPI === 'cli') {
            echo $line;
        }
    }

    private function append(string $line): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $handle = @fopen($this->logPath, 'ab');
            if ($handle === false) {
                usleep(50_000 * ($attempt + 1));
                continue;
            }

            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $line);
                flock($handle, LOCK_UN);
                fclose($handle);
                return;
            }

            fclose($handle);
            usleep(50_000 * ($attempt + 1));
        }
    }

    /**
     * @return list<string>
     */
    public function tail(int $lines = 50): array
    {
        if (!is_readable($this->logPath)) {
            return [];
        }

        $content = file_get_contents($this->logPath);
        if ($content === false || $content === '') {
            return [];
        }

        $all = array_filter(explode("\n", rtrim($content)));
        return array_slice($all, -$lines);
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    public function getLastRunAt(): ?string
    {
        if (!is_readable($this->logPath)) {
            return null;
        }

        $lines = $this->tail(1);
        if ($lines === []) {
            return null;
        }

        if (preg_match('/^\[([^\]]+)\]/', $lines[0], $m)) {
            return $m[1];
        }

        return null;
    }
}
