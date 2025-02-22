<?php

namespace Brahmic\ClientDTO\Support;

class Log
{
    private static ?Log $instance = null;
    private array $logs = [];

    public static function add($message): void
    {
        self::getInstance()->logs[] = $message;
    }

    public static function all(): array
    {
        return self::getInstance()->logs;
    }

    private static function getInstance(): self
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }
}
