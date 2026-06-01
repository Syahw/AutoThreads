<?php

namespace AutoThreads\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Missing or invalid authorization header');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            
            // Add user data to request attributes
            $request = $request->withAttribute('user_id', $decoded->sub);
            $request = $request->withAttribute('user_role', $decoded->role ?? 'user');
            $request = $request->withAttribute('user_email', $decoded->email ?? '');

            return $handler->handle($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            return $this->unauthorizedResponse('Token expired');
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid token');
        }
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => true,
            'message' => $message,
        ]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
