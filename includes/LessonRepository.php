<?php
declare(strict_types=1);

/**
 * Lesson JSON repository under data/lessons/{videoId}.json
 */
final class LessonRepository
{
    public function listAll(): array
    {
        $files = glob(LEARNYT_LESSONS . '/*.json') ?: [];
        $lessons = [];
        foreach ($files as $file) {
            $data = json_read($file);
            if (!is_array($data) || empty($data['video']['id'])) {
                continue;
            }
            $lessons[] = [
                'id' => $data['video']['id'],
                'title' => $data['video']['title'] ?? $data['video']['id'],
                'channel' => $data['video']['channel'] ?? '',
                'duration' => $data['video']['duration'] ?? 0,
                'thumbnail' => $data['video']['thumbnail'] ?? '',
                'url' => $data['video']['url'] ?? '',
                'segmentCount' => count($data['transcript'] ?? []),
                'chronoCount' => count($data['chronologicalToc'] ?? []),
                'learnCount' => count($data['learningToc'] ?? []),
                'diagramCount' => count($data['diagrams'] ?? []),
                'updatedAt' => $data['meta']['updatedAt'] ?? null,
                'file' => basename($file),
            ];
        }
        usort($lessons, static function ($a, $b) {
            return strcmp((string) ($b['updatedAt'] ?? ''), (string) ($a['updatedAt'] ?? ''));
        });
        return $lessons;
    }

    public function find(string $videoId): ?array
    {
        $videoId = $this->sanitizeId($videoId);
        if ($videoId === '') {
            return null;
        }
        $path = LEARNYT_LESSONS . '/' . $videoId . '.json';
        $data = json_read($path);
        return is_array($data) ? $data : null;
    }

    public function pathFor(string $videoId): string
    {
        return LEARNYT_LESSONS . '/' . $this->sanitizeId($videoId) . '.json';
    }

    public function sanitizeId(string $id): string
    {
        // YouTube IDs are typically 11 chars [A-Za-z0-9_-]; also allow sample-* demo ids
        $id = trim($id);
        if (preg_match('/^[A-Za-z0-9_-]{6,64}$/', $id)) {
            return $id;
        }
        return '';
    }
}
