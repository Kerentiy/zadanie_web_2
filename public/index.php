<?php

declare(strict_types=1);

use App\Application;
use App\Http\HttpLogger;
use App\Models\User;

require __DIR__ . '/../vendor/autoload.php';

$app = Application::boot(dirname(__DIR__));

// Log every incoming request + the response body we send back (channel "http").
HttpLogger::register($app->httpLog, $app->config['logging']['http']);

header('X-Request-Id: ' . $app->loggers->requestId());

/** Sends a JSON response. */
function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

try {
    switch (true) {
        // GET / — service info
        case $method === 'GET' && $path === '/':
            json_response([
                'app' => $app->config['app']['name'],
                'users_count' => User::count(), // Eloquent
                'endpoints' => ['GET /users', 'GET /users/{id}', 'POST /users'],
            ]);
            break;

        // GET /users — list (Eloquent)
        case $method === 'GET' && $path === '/users':
            json_response(['data' => User::query()->orderByDesc('id')->limit(50)->get()]);
            break;

        // GET /users/{id}
        case $method === 'GET' && preg_match('#^/users/(\d+)$#', $path, $m) === 1:
            $user = User::find((int) $m[1]);
            $user
                ? json_response(['data' => $user])
                : json_response(['error' => 'User not found'], 404);
            break;

        // POST /users {"name": "...", "email": "..."} — create (Eloquent)
        case $method === 'POST' && $path === '/users':
            $input = json_decode((string) file_get_contents('php://input'), true);
            $name = is_array($input) ? trim((string) ($input['name'] ?? '')) : '';
            $email = is_array($input) ? trim((string) ($input['email'] ?? '')) : '';

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'Required';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email is required';
            } elseif (User::where('email', $email)->exists()) {
                $errors['email'] = 'Already taken';
            }
            if ($errors !== []) {
                json_response(['errors' => $errors], 422);
                break;
            }

            $user = User::create(['name' => $name, 'email' => $email]);
            $app->log->info('User created', ['id' => $user->id]);
            json_response(['data' => $user], 201);
            break;

        default:
            json_response(['error' => 'Not found'], 404);
    }
} catch (Throwable $e) {
    // CRITICAL = unexpected exception (see Monolog "Log Levels")
    $app->log->critical($e->getMessage(), ['exception' => $e]);
    json_response(
        ['error' => 'Internal Server Error'] + ($app->config['app']['debug'] ? ['message' => $e->getMessage()] : []),
        500,
    );
}
