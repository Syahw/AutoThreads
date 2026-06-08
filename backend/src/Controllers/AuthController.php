<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\User;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;

class AuthController
{
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validate input
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return $this->jsonResponse($response, ['error' => true, 'messages' => $errors], 422);
        }

        // Check if email exists
        if (User::where('email', $data['email'])->exists()) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Email already registered',
            ], 409);
        }

        // Create user
        $user = User::create([
            'uuid' => Uuid::uuid4()->toString(),
            'email' => $data['email'],
            'password_hash' => hash_password($data['password']),
            'name' => $data['name'],
            'role' => 'user',
            'plan' => 'free',
        ]);

        $token = $this->generateToken($user);

        return $this->jsonResponse($response, [
            'message' => 'Registration successful',
            'user' => $user->toPublicArray(),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['email']) || empty($data['password'])) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Email and password are required',
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!password_hash_supported($user->password_hash)) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Password hash is incompatible with this server. Run: php database/reset-password.php your@email.com newpassword',
            ], 503);
        }

        if (!password_verify($data['password'], $user->password_hash)) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->is_active) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Account is deactivated',
            ], 403);
        }

        // Update last login
        $user->last_login_at = now();
        $user->save();

        $token = $this->generateToken($user);

        return $this->jsonResponse($response, [
            'message' => 'Login successful',
            'user' => $user->toPublicArray(),
            'token' => $token,
        ]);
    }

    public function refresh(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $refreshToken = $data['refresh_token'] ?? '';

        try {
            $decoded = JWT::decode($refreshToken, new \Firebase\JWT\Key($_ENV['JWT_SECRET'], 'HS256'));
            $user = User::find($decoded->sub);

            if (!$user || !$user->is_active) {
                return $this->jsonResponse($response, ['error' => true, 'message' => 'Invalid token'], 401);
            }

            $token = $this->generateToken($user);
            return $this->jsonResponse($response, ['token' => $token]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => true, 'message' => 'Invalid refresh token'], 401);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        // With JWT, logout is handled client-side by removing the token
        // Optionally, add token to a blacklist in Redis
        return $this->jsonResponse($response, ['message' => 'Logged out successfully']);
    }

    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $user = User::find($userId);

        if (!$user) {
            return $this->jsonResponse($response, ['error' => true, 'message' => 'User not found'], 404);
        }

        return $this->jsonResponse($response, ['user' => $user->toPublicArray()]);
    }

    private function generateToken(User $user): array
    {
        $now = time();

        $accessPayload = [
            'iss' => $_ENV['APP_URL'],
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + (int) ($_ENV['JWT_EXPIRY'] ?? 3600),
        ];

        $refreshPayload = [
            'iss' => $_ENV['APP_URL'],
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800),
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256'),
            'refresh_token' => JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256'),
            'expires_in' => (int) ($_ENV['JWT_EXPIRY'] ?? 3600),
        ];
    }

    private function validateRegistration(?array $data): array
    {
        $errors = [];
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        if (empty($data['password']) || strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        if (empty($data['name']) || strlen($data['name']) < 2) {
            $errors[] = 'Name is required';
        }
        return $errors;
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
