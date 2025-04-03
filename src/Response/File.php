<?php

namespace Brahmic\ClientDTO\Response;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class File implements Arrayable, Responsable
{
    protected string $filename;

    protected string $mimeType;

    protected string $size;

    protected string $humanReadableSize;

    protected bool $inline = false;

    protected string $defaultFilename = 'downloaded_file';

    protected string $path;

    public function __construct(string $path, string $mimeType)
    {
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->filename = basename($this->path);;
        $this->size = $this->getSize();;
        $this->humanReadableSize = $this->getHumanReadableSize();;
    }

    public function getHumanReadableSize(): string
    {
        $size = $this->getSize();
        $units = ['B', 'Kb', 'Mb', 'Gb', 'Tb'];
        $factor = floor((strlen((string)$size) - 1) / 3);

        return sprintf("%.2f %s", $size / pow(1024, $factor), $units[$factor]);
    }

    public function getSize(): int
    {
        return $this->size ??= filesize($this->path);
    }

    public function delete(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }


    public function prependFilename(string $filename, ?string $glue = ' '): self
    {
        $this->filename = $filename . ($glue ?? '') . $this->filename;
        return $this;
    }


    public function setDefaultFilename(string $filename): self
    {
        $this->defaultFilename = $filename;
        return $this;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function openInBrowser(bool $inline = true): self
    {
        if ($this->canBeDisplayedInBrowser() && $inline) {
            $this->inline = true;
        } else {
            $this->inline = false;
        }
        return $this;
    }

    public function toResponse($request): StreamedResponse
    {
        return $this->getResponse();
    }

    public function getResponse(): StreamedResponse
    {
        return response()->stream(function () {
            $handle = fopen($this->path, 'rb');
            if ($handle === false) {
                throw new \RuntimeException("Can't open file: {$this->path}");
            }
            while (!feof($handle)) {
                $chunk = fread($handle, 32768);
                if ($chunk === false) {
                    throw new \RuntimeException("File read error: {$this->path}");
                }
                echo $chunk;
                flush();
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => $this->mimeType,
            'Content-Disposition' => ($this->inline ? 'inline' : 'attachment') . '; filename="' . $this->getFilename() . '"',
        ]);
    }

    public function saveAs(string $destinationPath): bool
    {
        return copy($this->path, $destinationPath);
    }

    protected function canBeDisplayedInBrowser(): bool
    {
        return in_array($this->mimeType, [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg',
            'audio/mpeg', 'audio/ogg', 'audio/wav',
            'text/plain', 'text/html', 'text/css', 'application/javascript', 'application/json', 'application/xml'
        ]);
    }

    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'humanReadableSize' => $this->humanReadableSize,
            'path' => $this->path,
        ];
    }
}
