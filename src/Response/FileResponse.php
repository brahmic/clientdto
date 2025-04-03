<?php

namespace Brahmic\ClientDTO\Response;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use ZipArchive;

class FileResponse implements Arrayable, Responsable
{
    protected Response $response;

    protected ?File $file = null;
    protected ?FileCollection $fileCollection = null;
    protected TemporaryDirectory $tempDir;
    protected bool $isArchive;

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->tempDir = TemporaryDirectory::make();

        $this->file = $this->storeFile();
        $this->isArchive = $this->checkIfArchive();
    }

    protected function storeFile(): File
    {
        $filename = $this->extractFilename();
        $path = $this->tempDir->path($filename);
        file_put_contents($path, $this->response->body());

        return new File($path, $this->extractMimeType());
    }

    protected function checkIfArchive(): bool
    {
        $mimeType = $this->extractMimeType();
        $extension = pathinfo($this->file->getPath(), PATHINFO_EXTENSION);

        return str_contains($mimeType, 'zip') || $extension === 'zip';
    }

    public function isArchive(): bool
    {
        return $this->isArchive;
    }

    protected function extract(): FileCollection
    {
        $zipPath = $this->file->getPath();
        $extractPath = $this->tempDir->path('extracted');
        mkdir($extractPath, 0777, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            throw new \RuntimeException("Ошибка при распаковке архива.");
        }

        return new FileCollection($extractPath);
    }

    public function file(): ?File
    {
        return $this->isArchive ? $this->files()->file() : $this->file;
    }

    public function hasMultipleFiles(): bool
    {
        return $this->files()->count() > 1;
    }

    public function hasOneFile(): bool
    {
        return $this->files()->count() === 1;
    }

    public function files(): FileCollection
    {
        if ($this->isArchive() && !$this->fileCollection) {
            $this->fileCollection = FileCollection::createFromDir($this->extract());
        }

        return $this->fileCollection ??= new FileCollection([$this->file]);
    }

    protected function extractFilename(): string
    {
        $disposition = $this->response->header('Content-Disposition');
        if ($disposition && preg_match('/filename="(.+?)"/', $disposition, $matches)) {
            return $matches[1];
        }
        return 'downloaded_file';
    }

    protected function extractMimeType(): string
    {
        return $this->response->header('Content-Type') ?? 'application/octet-stream';
    }

    public function toResponse($request): \Symfony\Component\HttpFoundation\StreamedResponse|array
    {
        return $this->isArchive ? $this->files()->toResponse($request) : $this->file->toResponse($request);
    }

    public function toArray(): array
    {
        return $this->file()->toArray();
    }
}
