<?php

namespace Brahmic\ClientDTO\Response;

use Illuminate\Support\Collection;

class FileCollection extends Collection
{

    public static function createFromDir(string $directory): self
    {
        $files = [];
        foreach (scandir($directory) as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $directory . DIRECTORY_SEPARATOR . $file;
                $files[] = new File($filePath, mime_content_type($filePath));
            }
        }
        return new self($files);
    }

    public function file(): ?File
    {
        return $this->first();
    }

    public function files(): array
    {
        return $this->all();
    }

    public function getByName(string $name): ?File
    {
        return $this->first(fn(File $file) => $file->getFilename() === $name);
    }

    public function saveTo(string $destinationPath): void
    {
        foreach ($this as $file) {
            $file->saveAs("$destinationPath/{$file->getFilename()}");
        }
    }

    public function toArray(): FileCollection
    {
        return $this->map(function (File $file) {
            return $file->toArray();
        });
    }
}
