<?php

declare(strict_types=1);

namespace Ldi\LogViewer\Entities;

use Ldi\LogViewer\Helpers\LogParser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;


class LogEntryCollection extends LazyCollection
{

    public static function load(string $raw): self
    {
        return new static(function () use ($raw) {
            foreach (LogParser::parse($raw) as $entry) {
                list($level, $header, $stack) = array_values($entry);

                yield new LogEntry($level, $header, $stack);
            }
        });
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $page = request()->get('page', 1);
        $path = request()->url();

        return new LengthAwarePaginator(
            $this->forPage($page, $perPage),
            $this->count(),
            $perPage,
            $page,
            compact('path')
        );
    }

    /**
     * Get filtered log entries by level.
     *
     * @param  string  $level
     *
     * @return self
     */
    public function filterByLevel(string $level): self
    {
        return $this->filter(function(LogEntry $entry) use ($level) {
            return $entry->isSameLevel($level);
        });
    }

    /* @using */
    public function stats(): array
    {
        $counters = $this->initStats();

        foreach ($this->groupBy('level') as $level => $entries) {
            $counters[$level] = $count = count($entries);
            $counters['all'] += $count;
        }

        return $counters;
    }

    public function tree(bool $trans = false): array
    {
        $tree = $this->stats();

        array_walk($tree, function(&$count, $level) use ($trans) {
            $count = [
                'name'  => $trans ? log_levels()->get($level) : $level,
                'count' => $count,
            ];
        });

        return $tree;
    }

    /* @using */
    private function initStats(): array
    {
        $levels = array_merge_recursive(
            ['all'],
            array_keys(log_viewer()->levels(true))
        );

        return array_map(function () {
            return 0;
        }, array_flip($levels));
    }
}
