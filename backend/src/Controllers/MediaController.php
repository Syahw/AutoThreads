<?php

namespace AutoThreads\Controllers;

use AutoThreads\Services\Media\HookImageStorage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MediaController
{
    private HookImageStorage $storage;

    public function __construct()
    {
        $this->storage = new HookImageStorage();
    }

    /**
     * Public route — Meta fetches hook images from here (no JWT).
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $filename = $args['filename'] ?? '';
        $path = $this->storage->pathForFilename($filename);

        if ($path === null) {
            return $response->withStatus(404);
        }

        $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        $response->getBody()->write((string) file_get_contents($path));

        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
