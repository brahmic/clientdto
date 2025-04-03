<?php

namespace Brahmic\ClientDTO\Traits;

use GuzzleHttp\RequestOptions;

trait Files
{
    protected array $files = [];
    public function addFile(string $path, string $name, string $content)
    {
        $this->files[] = [];
    }

    /**
     * Добавить файлы (multipart/form-data)
     */
    public function withFiles(array $files): self
    {
        foreach ($files as $key => $file) {
            $this->files[$key] = fopen($file, 'r'); // Открываем файлы для передачи
        }
        $this->contentType = RequestOptions::MULTIPART;

        return $this;
    }
}
