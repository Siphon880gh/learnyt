<?php
declare(strict_types=1);

/**
 * Lesson JSON repository:
 * - Seed (committed): data/lessons/{id}.json
 * - Synced demo (gitignored): data/demo/lessons/{id}.json
 * Never write user syncs into the seed folder.
 */
final class LessonRepository
{
    const SOURCE_SEED = 'seed';
    const SOURCE_DEMO = 'demo';

    /**
     * @return string[]
     */
    public function seedIds()
    {
        $raw = defined('LEARNYT_SEED_IDS') ? LEARNYT_SEED_IDS : 'sample-spaced-rep';
        $parts = explode(',', (string) $raw);
        $ids = array();
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $ids[] = $p;
            }
        }
        return $ids;
    }

    public function isSeedId($videoId)
    {
        $id = $this->sanitizeId((string) $videoId);
        return $id !== '' && in_array($id, $this->seedIds(), true);
    }

    /**
     * List seed + demo lessons (not browser-local).
     *
     * @return array
     */
    public function listAll()
    {
        $byId = array();
        foreach ($this->listFromDir(LEARNYT_LESSONS, self::SOURCE_SEED) as $item) {
            $byId[$item['id']] = $item;
        }
        foreach ($this->listFromDir(LEARNYT_DEMO_LESSONS, self::SOURCE_DEMO) as $item) {
            // Seed always wins if same id somehow appears in demo
            if (isset($byId[$item['id']]) && $byId[$item['id']]['source'] === self::SOURCE_SEED) {
                continue;
            }
            $byId[$item['id']] = $item;
        }
        $lessons = array_values($byId);
        usort($lessons, static function ($a, $b) {
            return strcmp((string) ($b['updatedAt'] ?? ''), (string) ($a['updatedAt'] ?? ''));
        });
        return $lessons;
    }

    /**
     * @param string $videoId
     * @return array|null
     */
    public function find($videoId)
    {
        $videoId = $this->sanitizeId((string) $videoId);
        if ($videoId === '') {
            return null;
        }
        // Prefer seed, then demo
        $seedPath = LEARNYT_LESSONS . '/' . $videoId . '.json';
        $data = json_read($seedPath);
        if (is_array($data)) {
            $data['_source'] = self::SOURCE_SEED;
            return $data;
        }
        $demoPath = LEARNYT_DEMO_LESSONS . '/' . $videoId . '.json';
        $data = json_read($demoPath);
        if (is_array($data)) {
            $data['_source'] = self::SOURCE_DEMO;
            return $data;
        }
        return null;
    }

    /**
     * Where a lesson lives on disk, if anywhere.
     *
     * @return string|null seed|demo|null
     */
    public function sourceOf($videoId)
    {
        $videoId = $this->sanitizeId((string) $videoId);
        if ($videoId === '') {
            return null;
        }
        if (is_readable(LEARNYT_LESSONS . '/' . $videoId . '.json')) {
            return self::SOURCE_SEED;
        }
        if (is_readable(LEARNYT_DEMO_LESSONS . '/' . $videoId . '.json')) {
            return self::SOURCE_DEMO;
        }
        return null;
    }

    public function pathFor($videoId)
    {
        return LEARNYT_LESSONS . '/' . $this->sanitizeId((string) $videoId) . '.json';
    }

    public function demoPathFor($videoId)
    {
        return LEARNYT_DEMO_LESSONS . '/' . $this->sanitizeId((string) $videoId) . '.json';
    }

    /**
     * @param string $id
     * @return string
     */
    public function sanitizeId($id)
    {
        $id = trim((string) $id);
        if (preg_match('/^[A-Za-z0-9_-]{6,64}$/', $id)) {
            return $id;
        }
        return '';
    }

    /**
     * @param string $dir
     * @param string $source
     * @return array
     */
    private function listFromDir($dir, $source)
    {
        if (!is_dir($dir)) {
            return array();
        }
        $files = glob($dir . '/*.json') ?: array();
        $lessons = array();
        foreach ($files as $file) {
            $data = json_read($file);
            if (!is_array($data) || empty($data['video']['id'])) {
                continue;
            }
            $lessons[] = array(
                'id' => $data['video']['id'],
                'title' => isset($data['video']['title']) ? $data['video']['title'] : $data['video']['id'],
                'channel' => isset($data['video']['channel']) ? $data['video']['channel'] : '',
                'duration' => isset($data['video']['duration']) ? $data['video']['duration'] : 0,
                'thumbnail' => isset($data['video']['thumbnail']) ? $data['video']['thumbnail'] : '',
                'url' => isset($data['video']['url']) ? $data['video']['url'] : '',
                'segmentCount' => count(isset($data['transcript']) ? $data['transcript'] : array()),
                'chronoCount' => count(isset($data['chronologicalToc']) ? $data['chronologicalToc'] : array()),
                'learnCount' => count(isset($data['learningToc']) ? $data['learningToc'] : array()),
                'diagramCount' => count(isset($data['diagrams']) ? $data['diagrams'] : array()),
                'updatedAt' => isset($data['meta']['updatedAt']) ? $data['meta']['updatedAt'] : null,
                'file' => basename($file),
                'source' => $source,
            );
        }
        return $lessons;
    }
}
