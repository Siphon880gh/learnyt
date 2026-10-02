<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$repo = new LessonRepository();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$id = (string) ($_GET['v'] ?? $_GET['id'] ?? '');
if ($id !== '') {
    $lesson = $repo->find($id);
    if ($lesson === null) {
        json_response(['error' => 'Not found'], 404);
    }
    json_response(['lesson' => $lesson]);
}

json_response(['lessons' => $repo->listAll()]);
