<?php

namespace App\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes updates to the Socket.IO server (realtime/server.js).
 *
 * Updates are buffered and sent in one HTTP call when the request (or the queued job)
 * ends, duplicates removed. A failure is logged, never thrown: realtime is a bonus,
 * the source of truth stays the database (pages re-render from it).
 */
class Realtime
{
    /**
     * @var array<string, array{channels: list<string>, type: string, data: array<string, mixed>, message: ?string}>
     */
    private array $buffer = [];

    /**
     * @var list<array{channels: list<string>, type: string, data: array<string, mixed>, message: ?string}>
     */
    private array $sent = [];

    private bool $faked = false;

    /**
     * @param  list<string|null>  $channels
     * @param  array<string, mixed>  $data
     * @param  ?string  $throttle  key: at most one update of this kind every `realtime.throttle` seconds
     */
    public function push(array $channels, string $type, array $data = [], ?string $message = null, ?string $throttle = null): void
    {
        $channels = array_values(array_unique(array_filter($channels)));

        if ($channels === [] || (! $this->faked && ! config('realtime.enabled'))) {
            return;
        }

        if ($throttle !== null && ! Cache::add("realtime:throttle:{$throttle}", 1, max(1, config('realtime.throttle')))) {
            return;
        }

        $key = md5(json_encode([$channels, $type, $data, $message]));
        $this->buffer[$key] = compact('channels', 'type', 'data', 'message');

        if (count($this->buffer) >= 50) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $messages = array_values($this->buffer);
        $this->buffer = [];

        if ($this->faked) {
            array_push($this->sent, ...$messages);

            return;
        }

        try {
            Http::timeout(2)->connectTimeout(1)
                ->withToken((string) config('realtime.secret'))
                ->post(rtrim(config('realtime.publish_url'), '/').'/publish', ['messages' => $messages])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Realtime publish failed: '.$e->getMessage());
        }
    }

    /**
     * Tests: keep the updates in memory instead of sending them.
     */
    public function fake(): static
    {
        $this->faked = true;
        $this->sent = [];

        return $this;
    }

    /**
     * @return list<array{channels: list<string>, type: string, data: array<string, mixed>, message: ?string}>
     */
    public function sent(?string $type = null): array
    {
        $this->flush();

        return array_values(array_filter($this->sent, fn ($m) => $type === null || $m['type'] === $type));
    }
}
