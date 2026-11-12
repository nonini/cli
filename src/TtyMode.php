<?php

namespace nonini\Cli;

use RuntimeException;

use function escapeshellarg;
use function register_shutdown_function;
use function rtrim;
use function shell_exec;

class TtyMode
{
    protected ?string $originalMode = null;

    public function enableCanonicalMode(): static
    {
        $this->enableFeature('icanon');

        return $this;
    }

    public function disableCanonicalMode(): static
    {
        $this->disableFeature('icanon');

        return $this;
    }

    public function enableFeature(string ...$feature): void
    {
        $this->preserve();
        $cmd = 'stty ';
        foreach ($feature as $f) {
            $cmd .= escapeshellarg($f);
        }

        shell_exec($cmd);
    }

    public function disableFeature(string ...$feature): void
    {
        $this->preserve();
        $cmd = 'stty';
        foreach ($feature as $f) {
            $cmd .= ' -' . escapeshellarg($f);
        }

        shell_exec($cmd);
    }

    public function getCurrentMode(): string
    {
        $modeString = shell_exec('stty -g');
        if (! is_string($modeString)) {
            throw new RuntimeException('stty -g gave no string');
        }
        return rtrim($modeString, PHP_EOL);
    }

    /**
     * Helper allowing to call stty only once for the mose used flags, icanon and echo
     */
    public function setPreferredMode(bool $echo = true): static
    {
        $this->preserve();
        if ($echo) {
            $this->disableFeature('icanon');
        } else {
            $this->disableFeature('icanon', 'echo');
        }

        return $this;
    }

    /**
     * @internal
     */
    public function preserve(bool $force = false): void
    {
        if ($force || $this->originalMode === null) {
            $this->originalMode = $this->getCurrentMode();
            register_shutdown_function($this->restore(...));
        }
    }

    /**
     * @internal
     */
    public function restore(): void
    {
        if ($this->originalMode) {
            shell_exec('stty ' . escapeshellarg($this->originalMode));
            $this->originalMode = null;
        }
    }
}
