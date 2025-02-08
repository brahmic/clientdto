<?php


namespace Brahmic\ClientDTO\Response;

use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;


class File
{

    protected array $headers = [];
    protected ?string $filename = 'Документ';
    protected ?string $extension = null;
    protected ?string $content = null;

    public function __construct(ResponseInterface $response)
    {
        $this->extension = $this->resolveExtension($response);
        $this->setHeaders($response);
        $this->content = $response->getBody()->getContents();
    }

    protected function setHeaders($response)
    {
        $this->headers = array_intersect_key(
            $response->getHeaders(),
            array_flip(['Content-Type', 'Content-Length', 'Content-MD5', 'Content-Size', 'Date',])
        );

        $this->headers['Cache-Control'] = 'no-cache private';
        $this->headers['Content-Description'] = 'File Transfer';
        $this->setContentDisposition();
        //$this->headers['Content-Transfer-Encoding'] = 'binary';
    }

    private function setContentDisposition(): void
    {
        $attachment = null;//$this->extension === 'html' ? null : 'attachment; '; //при включении, файл не будет открываться в браузере
        $this->headers['Content-Disposition'] = $attachment.'filename="' . $this->getFilename() . '"; filename*=utf-8\'\'' . rawurlencode($this->getFilename()) . ';';
    }

    public function getFilename(): string
    {
        return $this->filename . '.' . $this->extension;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        $this->setContentDisposition();
        return $this;
    }

    /**
     * @return Response
     */
    public function response(): Response
    {
        return new Response($this->content, 200, $this->headers);
    }

    /**
     * @return string|null
     */
    public function getBase64(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $directory
     * @return $this
     */
    public function saveTo(string $directory = null): self
    {
        if ($directory && !is_dir($directory)) {
            $this->makeDirectory($directory, 0755, true);
        }

        $filepath = $directory
            ? ($directory . DIRECTORY_SEPARATOR . $this->getFilename())
            : $this->getFilename();

        file_put_contents($filepath, $this->getBase64());
        return $this;
    }

    /**
     * @param ResponseInterface $response
     * @return string|null
     */
    protected function resolveExtension(ResponseInterface $response): ?string
    {
        $contentType = explode(';', $response->getHeaderLine('Content-Type'), 2)[0];
        return Arr::get(explode('/', $contentType), 1);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @param bool $force
     * @return bool
     */
    protected function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    static public function makeFromResponse(ResponseInterface $response): File
    {
        return new static($response);
    }
}
