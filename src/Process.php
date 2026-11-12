<?php

namespace nonini\Cli;

use RuntimeException;

use function array_pop;
use function array_shift;
use function cli_set_process_title;
use function explode;
use function getcwd;
use function getenv;
use function implode;
use function pcntl_exec;
use function str_replace;
use function strlen;

use const DIRECTORY_SEPARATOR;

class Process
{
    protected static ?string $initialCwd = null;

    /**
     * Set the command/process title for this process
     */
    public static function setTitle(string $title): void
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        }
    }

    /**
     * Replace this process with a new instance of itself by executing the
     * very same binary with the very same parameters
     */
    public static function restart(): void
    {
        // _ is only available when executed via shell
        $binary = (string) static::getEnvValue('_');
        /** @var string[] $argv */
        $argv = (array) $_SERVER['argv'];
        if (empty($argv)) {
            throw new RuntimeException('Cannot restart, unable to determine my binary');
        }
        if (strlen($binary) === 0) {
            // Problem: this doesn't work if we changed working directory and
            // called the binary with a relative path. Something that doesn't
            // happen when started as a daemon, and when started manually we
            // should have $_ from our shell.
            $binary = static::absoluteFilename(array_shift($argv));
        } else {
            array_shift($argv);
        }
        pcntl_exec($binary, $argv, getenv());
    }

    /**
     * Get the given ENV variable, null if not available
     *
     * Returns an array with all ENV variables if no $key is given
     * @return array<string, string>|string|null
     */
    public static function getEnv(?string $key = null): array|string|null
    {
        if ($key !== null) {
            return self::getEnvValue($key);
        }

        return getenv();
    }

    /**
     * Get the given ENV variable, null if not available
     */
    public static function getEnvValue(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Get the path to the executed binary when starting this command
     *
     * This fails if we changed working directory and called the binary with a
     * relative path. Something that doesn't happen when started as a daemon.
     * When started manually we should have $_ from our shell.
     *
     * To be always on the safe side please call Process::getInitialCwd() once
     * after starting your process and before switching directory. That way we
     * preserve our initial working directory.
     */
    public static function getBinaryPath(): string
    {
        if (isset($_SERVER['_'])) {
            $binary = $_SERVER['_'];
            if (! is_string($binary)) {
                throw new RuntimeException('Cannot determine binary, $_SERVER[_] is not a string');
            }

            return $binary;
        } else {
            global $argv;

            return static::absoluteFilename($argv[0]);
        }
    }

    /**
     * The working directory as given by getcwd() the very first time we
     * called this method
     */
    public static function getInitialCwd(): string
    {
        if (self::$initialCwd === null) {
            $initialCwd = getcwd();
            if ($initialCwd === false) {
                throw new RuntimeException('Unable to determine initial CWD');
            }
            self::$initialCwd = $initialCwd;
        }

        return self::$initialCwd;
    }

    /**
     * Returns the absolute filename for the given file
     *
     * If relative, it's calculated in relation to the given working directory.
     * The current working directory is being used if null is given.
     */
    public static function absoluteFilename(string $filename, ?string $cwd = null): string
    {
        $filename = str_replace(
            DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $filename
        );
        if ($filename[0] === '.') {
            $filename = ($cwd ?: getcwd()) . DIRECTORY_SEPARATOR . $filename;
        }
        $parts = explode(DIRECTORY_SEPARATOR, $filename);
        $result = [];
        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($result);
                continue;
            }
            $result[] = $part;
        }

        return implode(DIRECTORY_SEPARATOR, $result);
    }
}
