<?php

namespace Brahmic\ClientDTO\Support;

class Log
{
    private static ?Log $instance = null;
    private array $logs = [];

    public function add($message): void
    {
        $this->logs[] = $message;
    }

    public function all(): array
    {
        return $this->logs;
    }

//    public static function getInstance(): self
//    {
//        if (null === static::$instance) {
//            static::$instance = new static();
//        }
//
//        return static::$instance;
//    }
}
