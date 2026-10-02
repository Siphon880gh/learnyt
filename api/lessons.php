<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$repo = new LessonRepository();
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method !== 'GET') {
    json_response(array('error' => 'Method not allowed'), 405);
}

$id = (string) (isset($_GET['v']) ? $_GET['v'] : (isset($_GET['id']) ? $_GET['id'] : ''));
if ($id !== '') {
    $lesson = $repo->find($id);
    if ($lesson === null) {
        json_response(array('error' => 'Not found', 'source' => null), 404);
    }
    $source = isset($lesson['_source']) ? $lesson['_source'] : $repo->sourceOf($id);
    json_response(array('lesson' => $lesson, 'source' => $source));
}

json_response(array(
    'lessons' => $repo->listAll(),
    'seedIds' => $repo->seedIds(),
));
