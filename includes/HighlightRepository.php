<?php
declare(strict_types=1);

/**
 * Per-video highlights: data/highlights/{videoId}.json
 */
final class HighlightRepository
{
    public function listForVideo($videoId)
    {
        $path = $this->resolveReadPath($videoId);
        $data = json_read($path, array('highlights' => array()));
        return is_array(isset($data['highlights']) ? $data['highlights'] : null) ? $data['highlights'] : array();
    }

    public function listAll()
    {
        $files = array_merge(
            glob(LEARNYT_HIGHLIGHTS . '/*.json') ?: array(),
            glob(LEARNYT_DEMO_HIGHLIGHTS . '/*.json') ?: array()
        );
        $all = array();
        $seen = array();
        foreach ($files as $file) {
            $data = json_read($file, array('highlights' => array()));
            $items = isset($data['highlights']) ? $data['highlights'] : array();
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $h) {
                $hid = isset($h['id']) ? $h['id'] : null;
                if ($hid && isset($seen[$hid])) {
                    continue;
                }
                if ($hid) {
                    $seen[$hid] = true;
                }
                $all[] = $h;
            }
        }
        usort($all, static function ($a, $b) {
            return strcmp((string) (isset($b['created']) ? $b['created'] : ''), (string) (isset($a['created']) ? $a['created'] : ''));
        });
        return $all;
    }

    public function create(string $videoId, array $input): array
    {
        $videoId = (new LessonRepository())->sanitizeId($videoId);
        if ($videoId === '') {
            throw new InvalidArgumentException('Invalid video id');
        }
        $text = trim((string) ($input['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('Highlight text is required');
        }

        $item = [
            'id' => uuid_v4(),
            'videoId' => $videoId,
            'text' => $text,
            'start' => (float) ($input['start'] ?? 0),
            'end' => (float) ($input['end'] ?? ($input['start'] ?? 0)),
            'segmentId' => $input['segmentId'] ?? null,
            'note' => isset($input['note']) ? trim((string) $input['note']) : null,
            'srsStatus' => $input['srsStatus'] ?? 'none', // none|queued|reviewing
            'created' => gmdate('c'),
        ];

        $path = $this->path($videoId);
        $store = json_read($path, ['videoId' => $videoId, 'highlights' => []]);
        if (!isset($store['highlights']) || !is_array($store['highlights'])) {
            $store['highlights'] = [];
        }
        $store['videoId'] = $videoId;
        $store['highlights'][] = $item;
        json_write($path, $store);
        return $item;
    }

    public function update(string $videoId, string $id, array $patch): ?array
    {
        $path = $this->path($videoId);
        $store = json_read($path, ['highlights' => []]);
        $found = null;
        foreach ($store['highlights'] as &$h) {
            if (($h['id'] ?? '') === $id) {
                foreach (['text', 'note', 'srsStatus', 'start', 'end', 'segmentId'] as $key) {
                    if (array_key_exists($key, $patch)) {
                        $h[$key] = $patch[$key];
                    }
                }
                $h['updated'] = gmdate('c');
                $found = $h;
                break;
            }
        }
        unset($h);
        if ($found === null) {
            return null;
        }
        json_write($path, $store);
        return $found;
    }

    public function delete(string $videoId, string $id): bool
    {
        $path = $this->path($videoId);
        $store = json_read($path, ['highlights' => []]);
        $before = count($store['highlights']);
        $store['highlights'] = array_values(array_filter(
            $store['highlights'],
            static function ($h) use ($id) {
                return ($h['id'] ?? '') !== $id;
            }
        ));
        if (count($store['highlights']) === $before) {
            return false;
        }
        json_write($path, $store);
        return true;
    }

    private function path($videoId)
    {
        $id = (new LessonRepository())->sanitizeId((string) $videoId);
        return LEARNYT_HIGHLIGHTS . '/' . $id . '.json';
    }

    private function demoPath($videoId)
    {
        $id = (new LessonRepository())->sanitizeId((string) $videoId);
        return LEARNYT_DEMO_HIGHLIGHTS . '/' . $id . '.json';
    }

    /**
     * Resolve store path: writable highlights/ first, else demo copy.
     */
    private function resolveReadPath($videoId)
    {
        $primary = $this->path($videoId);
        if (is_readable($primary)) {
            return $primary;
        }
        $demo = $this->demoPath($videoId);
        if (is_readable($demo)) {
            return $demo;
        }
        return $primary;
    }
}

