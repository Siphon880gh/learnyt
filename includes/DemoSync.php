<?php
declare(strict_types=1);

/**
 * Persist browser-local lessons into gitignored data/demo/.
 * Password is validated here only — never trust the client alone.
 */
final class DemoSync
{
    /**
     * Expected sync password from .env (defaults to "go" for this local demo app).
     *
     * @return string
     */
    public static function expectedPassword()
    {
        $v = env('SYNC_DEMO_PASSWORD', null);
        if ($v === null || $v === '') {
            return 'go';
        }
        return (string) $v;
    }

    /**
     * @param string $provided
     * @return bool
     */
    public static function passwordOk($provided)
    {
        $expected = self::expectedPassword();
        // timing-safe compare when available
        if (function_exists('hash_equals')) {
            return hash_equals($expected, (string) $provided);
        }
        return (string) $provided === $expected;
    }

    /**
     * @param array $payload lessons[], highlightsByVideo{}, srsItems[]
     * @return array result summary
     */
    public function sync(array $payload)
    {
        $repo = new LessonRepository();
        $lessons = isset($payload['lessons']) && is_array($payload['lessons']) ? $payload['lessons'] : array();
        $highlightsByVideo = isset($payload['highlightsByVideo']) && is_array($payload['highlightsByVideo'])
            ? $payload['highlightsByVideo'] : array();
        $srsItems = isset($payload['srsItems']) && is_array($payload['srsItems']) ? $payload['srsItems'] : array();

        if (count($lessons) === 0) {
            throw new InvalidArgumentException('No lessons to sync');
        }

        $written = array();
        $skippedSeed = array();
        $errors = array();

        foreach ($lessons as $lesson) {
            if (!is_array($lesson) || empty($lesson['video']['id'])) {
                $errors[] = 'Lesson missing video.id';
                continue;
            }
            $id = $repo->sanitizeId((string) $lesson['video']['id']);
            if ($id === '') {
                $errors[] = 'Invalid video id';
                continue;
            }
            if ($repo->isSeedId($id)) {
                $skippedSeed[] = $id;
                continue;
            }
            // Never write into data/lessons/
            unset($lesson['_source']);
            if (!isset($lesson['meta']) || !is_array($lesson['meta'])) {
                $lesson['meta'] = array();
            }
            $lesson['meta']['syncedAt'] = gmdate('c');
            $lesson['meta']['updatedAt'] = gmdate('c');
            $path = $repo->demoPathFor($id);
            if (!json_write($path, $lesson)) {
                $errors[] = 'Failed to write ' . $id;
                continue;
            }
            $written[] = $id;

            // Highlights for this video
            if (isset($highlightsByVideo[$id]) && is_array($highlightsByVideo[$id])) {
                $hlPath = LEARNYT_DEMO_HIGHLIGHTS . '/' . $id . '.json';
                json_write($hlPath, array(
                    'videoId' => $id,
                    'highlights' => array_values($highlightsByVideo[$id]),
                ));
            }
        }

        // Merge SRS items into demo srs store (and also main srs for review.php convenience)
        if (count($srsItems) > 0) {
            $this->mergeSrs(LEARNYT_DEMO_SRS_FILE, $srsItems);
            $this->mergeSrs(LEARNYT_SRS_FILE, $srsItems);
        }

        return array(
            'written' => $written,
            'skippedSeed' => $skippedSeed,
            'errors' => $errors,
            'lessonCount' => count($written),
        );
    }

    /**
     * @param string $path
     * @param array $items
     */
    private function mergeSrs($path, array $items)
    {
        $store = json_read($path, array('items' => array()));
        if (!isset($store['items']) || !is_array($store['items'])) {
            $store = array('items' => array());
        }
        $byId = array();
        foreach ($store['items'] as $existing) {
            if (isset($existing['id'])) {
                $byId[$existing['id']] = $existing;
            }
        }
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id']) || empty($item['text'])) {
                continue;
            }
            $byId[$item['id']] = $item;
        }
        $store['items'] = array_values($byId);
        $store['updatedAt'] = gmdate('c');
        json_write($path, $store);
    }
}
