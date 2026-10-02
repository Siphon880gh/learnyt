<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

ensure_same_origin();

$repo = new HighlightRepository();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $videoId = (string) ($_GET['v'] ?? '');
        if ($videoId === '') {
            json_response(['highlights' => $repo->listAll()]);
        }
        json_response(['videoId' => $videoId, 'highlights' => $repo->listForVideo($videoId)]);
    }

    if ($method === 'POST') {
        $body = request_json();
        $videoId = (string) ($body['videoId'] ?? $_GET['v'] ?? '');
        $item = $repo->create($videoId, $body);
        json_response(['ok' => true, 'highlight' => $item], 201);
    }

    if ($method === 'PATCH' || $method === 'PUT') {
        $body = request_json();
        $videoId = (string) ($body['videoId'] ?? '');
        $id = (string) ($body['id'] ?? '');
        $item = $repo->update($videoId, $id, $body);
        if ($item === null) {
            json_response(['error' => 'Not found'], 404);
        }
        json_response(['ok' => true, 'highlight' => $item]);
    }

    if ($method === 'DELETE') {
        $body = request_json();
        $videoId = (string) ($body['videoId'] ?? $_GET['v'] ?? '');
        $id = (string) ($body['id'] ?? $_GET['id'] ?? '');
        $ok = $repo->delete($videoId, $id);
        if (!$ok) {
            json_response(['error' => 'Not found'], 404);
        }
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Method not allowed'], 405);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    json_response(['error' => env('APP_DEBUG') === 'true' ? $e->getMessage() : 'Server error'], 500);
}
