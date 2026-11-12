<?php

namespace nonini\Cli;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function count;

class Spinner
{
    public const ASCII_SLASH = ['/', '-', '\\', '|'];
    public const ASCII_BOUNCING_CIRCLE = ['.', 'o', 'O', '°', 'O', 'o'];
    public const ROTATING_HALF_CIRCLE = ['◑', '◒', '◐', '◓'];
    public const ROTATING_EARTH = ['🌎', '🌏', '🌍'];
    public const ROTATING_MOON = ['🌑', '🌒', '🌓', '🌔', '🌕', '🌖', '🌗', '🌘'];
    public const UP_DOWN_BAR = [' ', '_', '▁', '▃', '▄', '▅', '▆', '▇', '▆', '▅', '▄', '▃', '▁'];
    public const CLOCK = ['🕐', '🕑', '🕒', '🕓', '🕔', '🕕', '🕖', '🕗', '🕘', '🕙', '🕚', '🕛'];
    public const WAVING_DOTS = ['⢄', '⢂', '⢁', '⡁', '⡈', '⡐', '⡠', '⡐', '⡈', '⡁', '⢁', '⢂'];
    public const ROTATING_DOTS = ['⣷', '⣯', '⣟', '⡿', '⢿', '⣻', '⣽', '⣾'];

    protected int $frame = -1;
    protected int $count;
    protected float $delay;

    /**
     * @param string[] $frames
     */
    public function __construct(protected array $frames = self::ASCII_SLASH)
    {
        $this->count = count($frames);
        $this->delay = ((int) (2 * 100 / $this->count)) / 100;
    }

    protected function getNextFrame(): string
    {
        $this->frame++;
        if ($this->frame >= $this->count) {
            $this->frame = 0;
        }

        return $this->frames[$this->frame];
    }

    /**
     * @template T
     * @param PromiseInterface<T> $promise
     * @return PromiseInterface<T>
     */
    public function spinWhile(PromiseInterface $promise, callable $renderer): PromiseInterface
    {
        $next = function () use ($renderer) {
            $renderer($this->getNextFrame());
        };
        $spinTimer = Loop::addPeriodicTimer($this->delay, $next);
        $deferred = new Deferred(function () use ($spinTimer) {
            Loop::cancelTimer($spinTimer);
        });
        Loop::futureTick($next);
        $wait = $deferred->promise();
        $cancel = function () use ($wait) {
            $wait->cancel();
        };
        $promise->then($cancel, $cancel);

        return $promise;
    }
}
