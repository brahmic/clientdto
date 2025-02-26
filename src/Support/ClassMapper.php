<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\AbstractResource;

class ClassMapper
{
    private $map = [];

    public function mapClasses(string $directory): void
    {
        $files = scandir($directory);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $className = pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($className)) {
                    $this->analyzeClass($className);
                }
            }
        }
    }

    private function analyzeClass(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        if ($reflection->isSubclassOf(AbstractResource::class)) {
            foreach ($reflection->getMethods() as $method) {
                if ($method->getReturnType() && !$method->getReturnType()->isBuiltin()) {
                    $returnType = $method->getReturnType()->getName();
                    if (is_subclass_of($returnType, AbstractRequest::class)) {
                        $this->addToMap($className, $returnType, $method->getName());
                    } elseif (is_subclass_of($returnType, AbstractResource::class)) {
                        $this->analyzeClass($returnType);
                    }
                }
            }
        }
    }

    private function addToMap(string $parentClass, string $childClass, string $methodName): void
    {
        if (!isset($this->map[$parentClass])) {
            $this->map[$parentClass] = [];
        }
        $this->map[$parentClass][$methodName] = $childClass;
    }

    public function getMap(): array
    {
        return $this->map;
    }
}
