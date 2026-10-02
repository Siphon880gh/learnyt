<?php
declare(strict_types=1);

/**
 * POST JSON: { password, lessons[], highlightsByVideo{}, srsItems[] }
 * Validates password in PHP only. Writes to gitignored data/demo/.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

ensure_same_origin();

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method !== 'POST') {
    json_response(array('error' => 'Method not allowed'), 405);
}

$body = request_json();
$password = isset($body['password']) ? (string) $body['password'] : '';

if (!DemoSync::passwordOk($password)) {
    json_response(array('error' => 'Wrong password. Sync cancelled.', 'code' => 'bad_password'), 403);
}

try {
    $sync = new DemoSync();
    $result = $sync->sync($body);
    json_response(array(
        'ok' => true,
        'message' => 'Synced to demo. Lessons are now available to any visitor of this app.',
        'result' => $result,
    ));
} catch (InvalidArgumentException $e) {
    json_response(array('error' => $e->getMessage()), 400);
} catch (Throwable $e) {
    $msg = env('APP_DEBUG') === 'true' ? $e->getMessage() : 'Server error';
    json_response(array('error' => $msg), 500);
}
