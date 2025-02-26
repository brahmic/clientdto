<?php

declare(strict_types=1);

namespace Brahmic\ClientDTO\Support;


use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\AbstractResource;
use ReflectionClass;
use ReflectionMethod;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Scanner
{
    private string $directory;
    private array $classMap = [];
    private array $relations = [];

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function scan(): void
    {
        $this->findClasses();
        $this->buildRelations();
        $this->tracePaths();
    }

    private function findClasses(): void
    {
        foreach (get_declared_classes() as $class) {
            $refClass = new ReflectionClass($class);
            if ($refClass->isSubclassOf(AbstractResource::class) ||
                //$refClass->isSubclassOf(AbstractGroup::class) ||
                $refClass->isSubclassOf(AbstractRequest::class) ||
                $class === ClientDTO::class) {
                $this->classMap[$class] = $refClass;
            }
        }
    }

    private function buildRelations(): void
    {
        dump($this->classMap);
        foreach ($this->classMap as $class => $refClass) {
            foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->hasReturnType()) {
                    $returnType = $method->getReturnType();
                    if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin()) {
                        $returnClass = $returnType->getName();
                        if (isset($this->classMap[$returnClass])) {
                            $this->relations[$class][$method->getName()] = $returnClass;
                        }
                    }
                }
            }
        }
    }

    private function tracePaths(): void
    {

        $paths = [];
        foreach ($this->relations as $class => $methods) {
            foreach ($methods as $method => $targetClass) {
                if (is_subclass_of($targetClass, AbstractRequest::class)) {
                    $path = $this->findPath(ClientDTO::class, $class, [$class]);
                    if ($path) {
                        $paths[$targetClass] = $path;
                    }
                }
            }
        }

        dump($paths);
    }

    private function findPath(string $start, string $end, array $visited): ?array
    {
        if ($start === $end) {
            return [$start];
        }

        if (!isset($this->relations[$start])) {
            return null;
        }

        foreach ($this->relations[$start] as $method => $nextClass) {
            if (!in_array($nextClass, $visited, true)) {
                $path = $this->findPath($nextClass, $end, [...$visited, $nextClass]);
                if ($path) {
                    return array_merge([$start . '::' . $method . '()'], $path);
                }
            }
        }
        return null;
    }
}
