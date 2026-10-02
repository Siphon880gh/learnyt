<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

ensure_same_origin();

$srs = new SrsEngine();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = (string) ($_GET['action'] ?? 'list');
        if ($action === 'due') {
            json_response(['items' => $srs->due()]);
        }
        if ($action === 'buckets') {
            json_response($srs->buckets());
        }
        if (isset($_GET['id'])) {
            $item = $srs->find((string) $_GET['id']);
            if ($item === null) {
                json_response(['error' => 'Not found'], 404);
            }
            json_response(['item' => $item]);
        }
        json_response(['items' => $srs->listAll()]);
    }

    if ($method === 'POST') {
        $body = request_json();
        $action = (string) ($body['action'] ?? 'create');

        if ($action === 'rate') {
            $id = (string) ($body['id'] ?? '');
            $rating = (string) ($body['rating'] ?? '');
            $item = $srs->rate($id, $rating);
            if ($item === null) {
                json_response(['error' => 'Not found'], 404);
            }
            json_response(['ok' => true, 'item' => $item]);
        }

        if ($action === 'delete') {
            $ok = $srs->delete((string) ($body['id'] ?? ''));
            if (!$ok) {
                json_response(['error' => 'Not found'], 404);
            }
            json_response(['ok' => true]);
        }

        // default: create
        $item = $srs->create($body);
        json_response(['ok' => true, 'item' => $item], 201);
    }

    json_response(['error' => 'Method not allowed'], 405);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    json_response(['error' => env('APP_DEBUG') === 'true' ? $e->getMessage() : 'Server error'], 500);
}
