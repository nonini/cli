<?php

namespace nonini\Cli;

use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use RuntimeException;

use function defined;
use function fstat;
use function is_resource;
use function register_shutdown_function;
use function stream_isatty;
use function stream_set_blocking;

class Tty
{
    protected ?ReadableResourceStream $stdin = null;
    protected ?WritableResourceStream $stdout = null;
    protected ?TtyMode $ttyMode = null;
    protected bool $echo = true;

    public function __construct()
    {
        register_shutdown_function($this->restore(...));
        Loop::futureTick($this->initialize(...));
    }

    public function setEcho(bool $echo): static
    {
        $this->echo = $echo;
        if ($this->ttyMode) {
            if ($echo) {
                $this->ttyMode->enableFeature('echo');
            } else {
                $this->ttyMode->disableFeature('echo');
            }
        }

        return $this;
    }

    public function stdin(): ReadableResourceStream
    {
        if ($this->stdin === null) {
            $this->assertValidStdin();
            $this->stdin = new ReadableResourceStream(STDIN);
        }

        return $this->stdin;
    }

    protected function hasStdin(): bool
    {
        return defined('STDIN') && is_resource(STDIN) && fstat(STDIN) !== false;
    }

    protected function assertValidStdin(): void
    {
        if (! $this->hasStdin()) {
            throw new RuntimeException('I have no STDIN');
        }
    }

    public function stdout(): WritableResourceStream
    {
        if ($this->stdout === null) {
            $this->assertValidStdout();
            $this->stdout = new WritableResourceStream(STDOUT);
        }

        return $this->stdout;
    }

    protected function hasStdout(): bool
    {
        return defined('STDOUT') && is_resource(STDOUT) && fstat(STDOUT) !== false;
    }

    protected function assertValidStdout(): void
    {
        if (! $this->hasStdout()) {
            throw new RuntimeException('I have no STDOUT');
        }
    }

    protected function initialize(): void
    {
        $this->ttyMode = new TtyMode();
        $this->ttyMode->setPreferredMode($this->echo);
    }

    public static function isSupported(): bool
    {
        return stream_isatty(STDIN);
    }

    public function restore(): void
    {
        if ($this->hasStdin()) {
            // ReadableResourceStream sets blocking to false, let's restore this
            stream_set_blocking(STDIN, true);
        }
    }
}
