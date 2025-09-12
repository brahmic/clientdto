<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Contracts\GroupedRequest;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;


class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{

    protected ClientResult $result;
    protected RequestResult $requestResult;

    protected string $message = 'Successful';

    protected int $status = 200;

    protected array $details = [];
    protected bool $grouped = false;

    protected static ?ClientResponse $lastResponse = null;

    public function __construct(RequestResult $requestResult)
    {
        $this->requestResult = $requestResult;

        $this->result = $this->modificatorResult($requestResult);

        $this->message = $requestResult->message;

        $this->status = $requestResult->statusCode;

        $this->details = $requestResult->details;

        $this->grouped = $requestResult->clientRequest instanceof GroupedRequest;

        self::$lastResponse = $this;
    }

    public function toArray(): array
    {
        $final = new ClientResult()
            ->set('message', $this->message)
            ->set('error', $this->hasError())
            ->set('result', $this->result->toArray())
            ->set('details', $this->details, $this->hasError() && !empty($this->details));

        return $final->toArray();
    }


    public function toResponse($request): JsonResponse|Response
    {
        $resolved = $this->resolved();

        if ($resolved instanceof FileResponse) {

            if ($resolved->hasOneFile()) {
                return $resolved->toResponse($request);
            }
        }

        return response()->json($this->toArray(), $this->status, [], JSON_UNESCAPED_UNICODE);
    }

    public function raw(): mixed
    {
        // Приоритет: сырые данные из кеша
        if ($this->requestResult->rawData !== null) {
            return $this->requestResult->rawData;
        }

        // Fallback: сырой HTTP ответ напрямую от сервера (для реальных запросов)
        return $this->requestResult->response?->body();
    }

    /**
     * Получить сырые данные как PHP массив
     *
     * @return array|null
     * @throws \JsonException
     */
    public function rawAsArray(): ?array
    {
        $rawData = $this->raw();

        if ($rawData === null) {
            return null;
        }

        if (is_string($rawData)) {
            // Обеспечиваем корректную UTF-8 кодировку для русских символов
            if (!mb_check_encoding($rawData, 'UTF-8')) {
                $rawData = mb_convert_encoding($rawData, 'UTF-8', 'auto');
            }

            return json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
        }

        // Если сырые данные уже массив (edge case)
        return is_array($rawData) ? $rawData : [$rawData];
    }

    /**
     * Получить сырые данные как PHP объект (stdClass)
     *
     * @return object|null
     * @throws \JsonException
     */
    public function rawAsObject(): ?object
    {
        $rawData = $this->raw();

        if ($rawData === null) {
            return null;
        }

        if (is_string($rawData)) {
            // Обеспечиваем корректную UTF-8 кодировку для русских символов
            if (!mb_check_encoding($rawData, 'UTF-8')) {
                $rawData = mb_convert_encoding($rawData, 'UTF-8', 'auto');
            }

            return json_decode($rawData, false, 512, JSON_THROW_ON_ERROR);
        }

        // Если сырые данные уже объект (edge case)
        return is_object($rawData) ? $rawData : (object)$rawData;
    }

    /**
     * Получить сырые данные как строку (алиас для raw())
     *
     * @return string|null
     */
    public function rawAsString(): ?string
    {
        $rawData = $this->raw();

        // Преобразуем в строку если нужно
        if ($rawData === null) {
            return null;
        }

        $stringData = is_string($rawData) ? $rawData : (string)$rawData;

        // Обеспечиваем корректную UTF-8 кодировку для русских символов
        if (!mb_check_encoding($stringData, 'UTF-8')) {
            $stringData = mb_convert_encoding($stringData, 'UTF-8', 'auto');
        }

        return $stringData;
    }

    public function resolved(): mixed
    {
        return $this->requestResult->resolved;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function hasError(): bool
    {
        return $this->requestResult->hasError();
    }

    public function isGrouped(): bool
    {
        return $this->grouped;
    }


    public static function getLastResponse(): ?ClientResponse
    {
        return self::$lastResponse;
    }

    private function modificatorResult(RequestResult $requestResult): ClientResult
    {
        if ($requestResult?->clientRequest instanceof GroupedRequest) {

            /** @var Collection $collection */
            if ($collection = $requestResult->resolved) {
                $collection->transform(function (RequestResult $requestResult) {
                    return $requestResult
                        ->modifyResult()
                        ->set('key', $requestResult->clientRequest->getKey());
                });
            }
        }

        return $this->modifyResult($requestResult->modifyResult());
    }

    private function ifFileResolved(): bool
    {
        return $this->requestResult->hasFile();
    }

    public function modifyResult(): ClientResult
    {
        return $this->requestResult->modifyResult();
    }

    /**
     * Сохранить сырые данные ответа (до преобразования в DTO)
     *
     * @param string|null $path Путь для сохранения. Если null - автогенерация
     * @param string $format Формат для данных: 'json', 'php', 'serialize'
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function saveAs(?string $path = null, string $format = 'json'): bool
    {
        $rawData = $this->raw(); // Используем тот же метод, что и raw()

        if ($rawData === null) {
            throw new \InvalidArgumentException('Cannot save: no raw data available');
        }

        // Сырые данные всегда сохраняем как данные (не файлы)
        return $this->saveDataAs($rawData, $path, $format);
    }

    /**
     * Сохранить обработанный результат ответа (resolved DTO)
     *
     * @param string|null $path Путь для сохранения. Если null - автогенерация
     * @param string $format Формат для DTO/массивов: 'json', 'php', 'serialize'
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function saveResolvedAs(?string $path = null, string $format = 'json'): bool
    {
        $resolved = $this->resolved();

        if ($resolved === null) {
            throw new \InvalidArgumentException('Cannot save: no resolved data available');
        }

        // Для файлов - делегируем в FileResponse
        if ($resolved instanceof FileResponse) {
            return $this->saveFileResponse($resolved, $path);
        }

        // Для DTO/массивов - наша логика
        return $this->saveDataAs($resolved, $path, $format);
    }

    /**
     * Сохранить FileResponse
     */
    private function saveFileResponse(FileResponse $fileResponse, ?string $path): bool
    {
        if (!$fileResponse->hasOneFile() && !$fileResponse->hasMultipleFiles()) {
            throw new \RuntimeException('FileResponse contains no files');
        }

        // Для одного файла
        if ($fileResponse->hasOneFile()) {
            if ($path === null) {
                $path = $this->generateDefaultPath('file');
            } else {
                $path = $this->resolveFilePath($path);
            }
            return $fileResponse->file()->saveAs($path);
        }

        // Для множественных файлов - сохраняем в директорию
        if ($fileResponse->hasMultipleFiles()) {
            if ($path === null) {
                $directory = $this->generateDefaultDirectory();
            } else {
                $directory = $this->resolveFilePath(dirname($path));
            }
            $fileResponse->files()->saveTo($directory);
            return true;
        }

        return false;
    }

    /**
     * Сохранить DTO или массив
     */
    private function saveDataAs(mixed $data, ?string $path, string $format): bool
    {
        if ($path === null) {
            $extension = match($format) {
                'php' => 'php',
                'serialize' => 'ser',
                default => 'json'
            };
            $path = $this->generateDefaultPath($extension);
        } else {
            // Преобразуем относительные пути в абсолютные относительно storage/app
            $path = $this->resolveFilePath($path);
        }

        $content = match($format) {
            'json' => $this->serializeToJson($data),
            'php' => $this->serializeToPHP($data),
            'serialize' => serialize($data),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Сериализовать в JSON
     */
    private function serializeToJson(mixed $data): string
    {
        // Если данные уже JSON строка - декодируем её сначала
        if (is_string($data)) {
            try {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                $data = $decoded;
            } catch (\JsonException $e) {
                // Если не JSON строка - оставляем как есть
            }
        }

        // Для DTO объектов сначала получаем массив, чтобы контролировать кодировку
        if (!is_array($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        // Принудительно кодируем в UTF-8 с правильными флагами
        $json = json_encode($data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        // Дополнительная проверка кодировки
        if (!mb_check_encoding($json, 'UTF-8')) {
            $json = mb_convert_encoding($json, 'UTF-8', 'auto');
        }

        return $json;
    }

    /**
     * Сериализовать в PHP
     */
    private function serializeToPHP(mixed $data): string
    {
        // Для DTO объектов сначала конвертируем в массив
        if (method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        return "<?php\n\nreturn " . var_export($data, true) . ";\n";
    }

    /**
     * Сгенерировать путь по умолчанию для файла
     * Используется в saveAs() и saveResolvedAs()
     */
    private function generateDefaultPath(string $extension): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $directory = storage_path('app/clientdto-responses');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return "{$directory}/response_{$timestamp}.{$extension}";
    }

    /**
     * Сгенерировать директорию по умолчанию для множественных файлов
     */
    private function generateDefaultDirectory(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $directory = storage_path("app/clientdto-responses/files_{$timestamp}");

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    /**
     * Сохранить сырые данные ответа на диск
     *
     * @param string|null $path Путь для сохранения (null = автогенерация).
     *                          Относительный путь считается от корня приложения.
     * @return bool Успешность сохранения
     * @throws \InvalidArgumentException Если нет сырых данных
     */
    public function saveRawAs(?string $path = null): bool
    {
        $rawData = $this->raw();

        if ($rawData === null) {
            throw new \InvalidArgumentException('No raw data available');
        }

        if ($path === null) {
            $path = storage_path('app/cache_dumps/' . now()->format('Y-m-d_H-i-s') . '.json');
        } else {
            // Относительные пути от корня приложения
            $path = $this->resolveFilePath($path);
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($path, $rawData) !== false;
    }

    /**
     * Преобразовать путь в абсолютный путь
     */
    private function resolveFilePath(string $path): string
    {
        // Если уже абсолютный путь - возвращаем как есть
        if (str_starts_with($path, '/') || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:/i', $path))) {
            return $path;
        }

        // Относительный путь - делаем относительно КОРНЯ приложения
        return base_path(ltrim($path, '/'));
    }
}
