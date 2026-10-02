<?php
declare(strict_types=1);

/**
 * Per-video highlights: data/highlights/{videoId}.json
 */
final class HighlightRepository
{
    public function listForVideo(string $videoId): array
    {
        $path = $this->path($videoId);
        $data = json_read($path, ['highlights' => []]);
        return is_array($data['highlights'] ?? null) ? $data['highlights'] : [];
    }

    public function listAll(): array
    {
        $files = glob(LEARNYT_HIGHLIGHTS . '/*.json') ?: [];
        $all = [];
        foreach ($files as $file) {
            $data = json_read($file, ['highlights' => []]);
            $items = $data['highlights'] ?? [];
            if (is_array($items)) {
                foreach ($items as $h) {
                    $all[] = $h;
                }
            }
        }
        usort($all, static fn($a, $b) => strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? '')));
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
            static fn($h) => ($h['id'] ?? '') !== $id
        ));
        if (count($store['highlights']) === $before) {
            return false;
        }
        json_write($path, $store);
        return true;
    }

    private function path(string $videoId): string
    {
        $id = (new LessonRepository())->sanitizeId($videoId);
        return LEARNYT_HIGHLIGHTS . '/' . $id . '.json';
    }
}
