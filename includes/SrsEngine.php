<?php
declare(strict_types=1);

/**
 * SM-2 inspired spaced repetition engine.
 * Ratings: again | hard | good | easy
 * Store: data/srs.json
 */
final class SrsEngine
{
    private const DEFAULT_EASE = 2.5;
    private const MIN_EASE = 1.3;

    public function listAll(): array
    {
        $store = $this->load();
        return $store['items'] ?? [];
    }

    public function due(?string $nowIso = null): array
    {
        $now = $nowIso ? strtotime($nowIso) : time();
        $items = $this->listAll();
        $due = array_filter($items, static function ($item) use ($now) {
            $next = strtotime((string) ($item['nextReview'] ?? '1970-01-01'));
            return $next !== false && $next <= $now;
        });
        usort($due, static function ($a, $b) {
            return strcmp((string) $a['nextReview'], (string) $b['nextReview']);
        });
        return array_values($due);
    }

    public function buckets(): array
    {
        $now = time();
        $todayEnd = strtotime('tomorrow', $now) - 1;
        $weekEnd = strtotime('+7 days', $now);
        $items = $this->listAll();

        $dueToday = [];
        $overdue = [];
        $upcoming = [];
        $recent = [];

        foreach ($items as $item) {
            $next = strtotime((string) ($item['nextReview'] ?? '1970-01-01')) ?: 0;
            $created = strtotime((string) ($item['created'] ?? '')) ?: 0;

            if ($next < strtotime('today', $now)) {
                $overdue[] = $item;
            } elseif ($next <= $todayEnd) {
                $dueToday[] = $item;
            } elseif ($next <= $weekEnd) {
                $upcoming[] = $item;
            }

            if ($created >= strtotime('-7 days', $now)) {
                $recent[] = $item;
            }
        }

        usort($overdue, static function ($a, $b) {
            return strcmp((string) $a['nextReview'], (string) $b['nextReview']);
        });
        usort($dueToday, static function ($a, $b) {
            return strcmp((string) $a['nextReview'], (string) $b['nextReview']);
        });
        usort($upcoming, static function ($a, $b) {
            return strcmp((string) $a['nextReview'], (string) $b['nextReview']);
        });
        usort($recent, static function ($a, $b) {
            return strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? ''));
        });

        return [
            'dueToday' => $dueToday,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'recent' => array_slice($recent, 0, 20),
            'all' => $items,
            'history' => $this->recentHistory(30),
        ];
    }

    public function create(array $input): array
    {
        $text = trim((string) ($input['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('SRS text is required');
        }
        $videoId = (string) ($input['videoId'] ?? '');
        $repo = new LessonRepository();
        if ($videoId !== '' && $repo->sanitizeId($videoId) === '') {
            throw new InvalidArgumentException('Invalid video id');
        }

        $now = gmdate('c');
        $item = [
            'id' => uuid_v4(),
            'text' => $text,
            'videoId' => $videoId !== '' ? $repo->sanitizeId($videoId) : null,
            'start' => isset($input['start']) ? (float) $input['start'] : null,
            'end' => isset($input['end']) ? (float) $input['end'] : null,
            'segmentId' => $input['segmentId'] ?? null,
            'sourceType' => $input['sourceType'] ?? 'highlight', // highlight|chrono|learn|concept|manual
            'sourceTitle' => $input['sourceTitle'] ?? null,
            'note' => isset($input['note']) ? trim((string) $input['note']) : null,
            'created' => $now,
            'lastReviewed' => null,
            'nextReview' => $now, // due immediately for first review
            'interval' => 0, // days
            'ease' => self::DEFAULT_EASE,
            'reviewCount' => 0,
            'history' => [],
        ];

        $store = $this->load();
        $store['items'][] = $item;
        $this->save($store);
        return $item;
    }

    public function rate(string $id, string $rating): ?array
    {
        $rating = strtolower(trim($rating));
        if (!in_array($rating, ['again', 'hard', 'good', 'easy'], true)) {
            throw new InvalidArgumentException('Invalid rating');
        }

        $store = $this->load();
        $found = null;
        foreach ($store['items'] as &$item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            $item = $this->applySm2($item, $rating);
            $found = $item;
            break;
        }
        unset($item);

        if ($found === null) {
            return null;
        }
        $this->save($store);
        return $found;
    }

    public function find(string $id): ?array
    {
        foreach ($this->listAll() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $store = $this->load();
        $before = count($store['items']);
        $store['items'] = array_values(array_filter(
            $store['items'],
            static function ($i) use ($id) {
                return ($i['id'] ?? '') !== $id;
            }
        ));
        if (count($store['items']) === $before) {
            return false;
        }
        $this->save($store);
        return true;
    }

    private function applySm2(array $item, string $rating): array
    {
        $ease = (float) ($item['ease'] ?? self::DEFAULT_EASE);
        $interval = (float) ($item['interval'] ?? 0);
        $reps = (int) ($item['reviewCount'] ?? 0);

        // Quality mapping similar to Anki
        // again=0, hard=2, good=3, easy=4 (SM-2 scale 0-5)
        $qMap = ['again' => 0, 'hard' => 2, 'good' => 3, 'easy' => 4];
        $q = $qMap[$rating];

        if ($q < 3) {
            // Failed / again / hard-fail path: reset interval
            if ($rating === 'again') {
                $reps = 0;
                $interval = 0; // review again soon (~10 min conceptually; we use same day)
                $ease = max(self::MIN_EASE, $ease - 0.2);
                $nextDays = 0; // due again today — we schedule +10 minutes
                $nextTs = time() + 10 * 60;
            } else {
                // hard: keep some progress but shorten
                $reps += 1;
                $ease = max(self::MIN_EASE, $ease - 0.15);
                if ($reps === 1) {
                    $interval = 1;
                } elseif ($reps === 2) {
                    $interval = 3;
                } else {
                    $interval = max(1, round($interval * 1.2));
                }
                $nextDays = $interval;
                $nextTs = time() + (int) round($nextDays * 86400);
            }
        } else {
            $reps += 1;
            if ($rating === 'easy') {
                $ease = $ease + 0.15;
            } elseif ($rating === 'good') {
                $ease = $ease + 0.0;
            }

            if ($reps === 1) {
                $interval = $rating === 'easy' ? 4 : 1;
            } elseif ($reps === 2) {
                $interval = $rating === 'easy' ? 7 : 3;
            } else {
                $multiplier = $rating === 'easy' ? ($ease + 0.15) : $ease;
                $interval = max(1, round($interval * $multiplier));
            }
            $nextDays = $interval;
            $nextTs = time() + (int) round($nextDays * 86400);
        }

        $now = gmdate('c');
        $item['ease'] = round($ease, 2);
        $item['interval'] = (int) $interval;
        $item['reviewCount'] = $reps;
        $item['lastReviewed'] = $now;
        $item['nextReview'] = gmdate('c', $nextTs);
        $item['history'] = $item['history'] ?? [];
        $item['history'][] = [
            'rating' => $rating,
            'at' => $now,
            'interval' => $item['interval'],
            'ease' => $item['ease'],
        ];
        // Cap history length
        if (count($item['history']) > 50) {
            $item['history'] = array_slice($item['history'], -50);
        }
        return $item;
    }

    private function recentHistory(int $limit): array
    {
        $events = [];
        foreach ($this->listAll() as $item) {
            foreach ($item['history'] ?? [] as $h) {
                $events[] = [
                    'itemId' => $item['id'],
                    'text' => $item['text'],
                    'videoId' => $item['videoId'] ?? null,
                    'rating' => $h['rating'] ?? null,
                    'at' => $h['at'] ?? null,
                ];
            }
        }
        usort($events, static function ($a, $b) {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });
        return array_slice($events, 0, $limit);
    }

    private function load(): array
    {
        $data = json_read(LEARNYT_SRS_FILE, ['items' => []]);
        if (!isset($data['items']) || !is_array($data['items'])) {
            $data = ['items' => []];
        }
        return $data;
    }

    private function save(array $store): void
    {
        $store['updatedAt'] = gmdate('c');
        json_write(LEARNYT_SRS_FILE, $store);
    }
}
