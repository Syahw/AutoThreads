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

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
