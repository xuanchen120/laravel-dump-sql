<?php

/*
 * This file is part of the guanguans/laravel-dump-sql.
 *
 * (c) guanguans <ityaozm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Guanguans\LaravelDumpSql\Handlers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * This file is modified from `overtrue/laravel-query-logger`.
 */
class ListenedSqlHandler
{
    public function __invoke(string $target): void
    {
        if (! in_array($target, ['log', 'dump', 'dd'])) {
            throw new InvalidArgumentException('Invalid target argument.');
        }

        DB::listen(function (QueryExecuted $query) use ($target) {
            $sqlWithPlaceholders = str_replace(['%', '?', '%s%s'], ['%%', '%s', '?'], $query->sql);

            $bindings = $query->connection->prepareBindings($query->bindings);
            $pdo = $query->connection->getPdo();
            $realSql = $sqlWithPlaceholders;
            $duration = $this->formatDuration($query->time / 1000);

            if (count($bindings) > 0) {
                $realSql = vsprintf($sqlWithPlaceholders, array_map([$pdo, 'quote'], $bindings));
            }

            $sqlInfo = sprintf(
                '[%s] [%s] %s | %s: %s',
                $query->connection->getDatabaseName(),
                $duration,
                $realSql,
                request()->method(),
                request()->getRequestUri()
            );

            switch ($target) {
                case 'log':
                    Log::channel(config('logging.default'))->debug($sqlInfo);
                    break;
                case 'dump':
                    dump($sqlInfo);
                    break;
                case 'dd':
                    dd($sqlInfo);
                    break;
            }
        });
    }

    /**
     * @param float $seconds
     *
     * @return string
     */
    private function formatDuration($seconds)
    {
        if ($seconds < 0.001) {
            return round($seconds * 1000000).'μs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 2).'ms';
        }

        return round($seconds, 2).'s';
    }
}