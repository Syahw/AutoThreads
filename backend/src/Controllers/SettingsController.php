<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController
{
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $user = User::find($userId);

        $settings = $user->settings ? json_decode($user->settings, true) : [];

        return $this->json($response, ['data' => $settings]);
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        $user = User::find($userId);

        $currentSettings = $user->settings ? json_decode($user->settings, true) : [];
        $mergedSettings = array_merge($currentSettings, $data);

        if (array_key_exists('schedule_presets', $data)) {
            $presets = $data['schedule_presets'];
            if ($presets === null || $presets === [] || $presets === '') {
                unset($mergedSettings['schedule_presets']);
            } elseif (is_array($presets)) {
                $mergedSettings['schedule_presets'] = $this->sanitizeSchedulePresets($presets);
            }
        }

        $user->settings = json_encode($mergedSettings);
        $user->save();

        return $this->json($response, ['message' => 'Settings updated', 'data' => $mergedSettings]);
    }

    public function blacklist(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $user = User::find($userId);

        $settings = $user->settings ? json_decode($user->settings, true) : [];
        $blacklist = $settings['blacklist'] ?? [];

        return $this->json($response, ['data' => $blacklist]);
    }

    public function addBlacklistWord(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        $user = User::find($userId);

        $settings = $user->settings ? json_decode($user->settings, true) : [];
        $blacklist = $settings['blacklist'] ?? [];

        if (!empty($data['word']) && !in_array($data['word'], $blacklist)) {
            $blacklist[] = $data['word'];
        }

        $settings['blacklist'] = $blacklist;
        $user->settings = json_encode($settings);
        $user->save();

        return $this->json($response, ['message' => 'Word added to blacklist', 'data' => $blacklist]);
    }

    /**
     * @param array<int, array<string, mixed>> $presets
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeSchedulePresets(array $presets): array
    {
        $allowedTypes = ['minutes_from_now', 'today_at', 'tomorrow_at', 'next_midnight'];
        $clean = [];

        foreach ($presets as $index => $preset) {
            if (!is_array($preset) || empty($preset['label']) || empty($preset['type'])) {
                continue;
            }

            $type = (string) $preset['type'];
            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }

            $entry = [
                'id' => !empty($preset['id']) ? (string) $preset['id'] : 'preset-' . $index,
                'label' => trim((string) $preset['label']),
                'type' => $type,
            ];

            if ($type === 'minutes_from_now') {
                $entry['minutes'] = max(1, min(10080, (int) ($preset['minutes'] ?? 60)));
            } else {
                $entry['hour'] = max(0, min(23, (int) ($preset['hour'] ?? 0)));
                $entry['minute'] = max(0, min(59, (int) ($preset['minute'] ?? 0)));
            }

            $clean[] = $entry;
        }

        return $clean;
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
