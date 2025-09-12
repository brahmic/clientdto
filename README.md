## ClientDTO

#### What does it do?

- ✅ Integration with external API
- ✅ Error and state handling, always predictable response
- ✅ Request grouping
- ✅ Working with files and archives
- ✅ Support for queries with pagination
- ✅ Additional query attempts with flexible configuration
- ✅ **HTTP Request Caching** - intelligent caching with RAW/DTO modes
- ✅ Simplifies working with API

### Using examples

```php
    // In the Controller
    // Get resource and sets the data from the Request to the Children request 
    public function (Person $person, Request $request) {
        return $person->children()->set($request)->send();
    }

    // The request data will be automatically filled in from the Request.
    public function (Children $children) {
        return $children->send();
    }

```

```php
    // Returns CustomResponse (extended of ClientResponse, implements Illuminate\Contracts\Support\Responsable)
    $customClient->person()->children()->send()

    // Returns null or the received answer as DTO object - MyPageableResultDto in this case.
    $customClient->person()->children()->send()->resolved()


    /** @var FileResponse|null $fileResponse */
    $fileResponse = $customClient->person()->report()->send()->->resolved()
      
    // Also we can get size, MIME type, set new name, etc.
    // If it is an archive, it will be automatically unpacked.
    $fileResponse->file()
        ->openInBrowser(true)
        ->saveAs('path/someReport');
        
    $fileResponse->files()->saveTo('path/someReport');
```

### Custom client example

```php
class CustomClient extends ClientDTO
{

    public function __construct(array $config = [])
    {
        $this
            ->cache(false)
            ->setBaseUrl('https://customapiservice.com/')
            ->setTimeout(60)
            ->requestCache()                    // Enable HTTP request caching
            ->requestCacheRaw()                 // Enable RAW response caching
            ->postIdempotent()                  // Allow POST request caching
            ->requestCacheTtl(3600)             // Cache TTL: 1 hour
            ->requestCacheSize(5 * 1024 * 1024) // Cache size limit: 5MB
            ->onClearCache(function () {
                $this->report()->clearCache();
            })
            ->setDebug(app()->hasDebugModeEnabled());
    }

    
    public function person(): Person
    {
        return new Person();
    }


    //Primary processing of the response, all further resources along the chain receive the transformed response.
    public static function handle(array $data): mixed
    {
        if (array_key_exists('response', $data)) {
            return DefaultDto::from($data);
        }
        
        if (array_key_exists('query_type', $data) && array_key_exists('uuid', $data)) {
            return OtherDto::from($data);
        }

        return $data;
    }

    /**
     * @throws \Exception
     */
    public function validation(mixed $data, AbstractRequest $abstractRequest, Response $response): mixed
    {
        if ($data instanceof DefaultDto) {

            $exceptionTitle = $data->statusTitle();

            return match ($data->status) {
                SecondaryStatus::AnswerReceived->value => true,
                SecondaryStatus::WaitingForAResponse->value => throw new AttemptNeededException($exceptionTitle, 202),
                SecondaryStatus::SourceUnavailable->value => throw new AttemptNeededException($exceptionTitle, 523),
                SecondaryStatus::CriticalError->value => throw new Exception($exceptionTitle, 500),
                SecondaryStatus::UnknownError->value, => throw new Exception($exceptionTitle, 520),
                default => throw new \Exception($exceptionTitle, 500)
            };
        }

        if ($abstractRequest instanceof Report) {
            return $data;
        }

        if ($response->getStatusCode() === 200) {
            if ($status = Arr::get($data, 'status')) {
                SecondaryStatus::check($status);
            }

            throw new UnresolvedResponseException("Response received but not recognized", $response);
        }

        throw new Exception("Unknown request. Check the request parameters, the request processing chain, including data processing via the `handle` method.", 500);
    }

    public function beforeExecute(AbstractRequest $request): void
    {
        if ($request instanceof CheckRequest) {
            $this->addQueryParam('token', '123bbd2113da3s3f1xc23c8b4927');
        }
    }

    public function getResponseClass(): string
    {
        return CustomResponse::class;
    }
}
```

### Resource example

```php
class Person extends AbstractResource
{
    public const string NAME = 'Person';    //optional

    public function children(): Children
    {
        return new Children();
    }
    
    public function report(): PersonReport
    {
        return new PersonReport();
    }
}
```

### Request example

```php
class Children extends GetRequest implements PaginableRequestInterface
{
    use Uuid, Paginable;

    public const string NAME = "The person's children"; //optional

    #[Wrapped(CaseDto::class)]
    protected ?string $dto = MyPageableResultDto::class;

    public const string URI = 'person/{uuid}/children';


    //Optional. This name will be used when sending a request to the remote API instead of "filterText"
    #[MapOutputName('filter_text')]
    //Optional. This information can be extracted later to create a registry of queries created. 
    #[Filter(title: 'Search string', description: 'Enter text', note: 'Search by name')]
    public ?string $filterText = null;

    public function set(
        string      $uuid,
        ?int        $page = null,
        ?int        $rows = null,        
        ?string     $filterText = null,        
    ): static
    {
        return $this->assignSetValues();
    }
}

```

### File Request example

```php
class PersonReport extends GetRequest
{
use Uuid;

    public const string NAME = 'Get report';    //optional

    public const string URI = 'person/{uuid}/report';

    protected ?string $dto = FileResponse::class;

    public Format $event = Format::Pdf;

    public function set(string $uuid, ?Format $event = null): static
    {
        return $this->assignSetValues();
    }

    public function postProcess(FileResponse $fileResponse): void
    {
        $fileResponse->file()->openInBrowser(false)->prependFilename(self::NAME);
    }
}
```

## HTTP Request Caching

ClientDTO provides intelligent HTTP request caching with support for both RAW and DTO modes.

### Basic Configuration

```php
class MyClient extends ClientDTO
{
    public function __construct()
    {
        $this
            ->requestCache()                    // Enable HTTP request caching
            ->requestCacheRaw()                 // Enable RAW response caching (optional)
            ->postIdempotent()                  // Allow POST request caching (optional)
            ->requestCacheTtl(3600)             // Cache TTL: 1 hour (optional)
            ->requestCacheSize(5 * 1024 * 1024); // Cache size limit: 5MB (optional)
    }
}
```

### Caching Methods

| Method | Description | Default |
|--------|-------------|---------|
| `requestCache()` | Enable HTTP request caching | `false` |
| `requestCacheRaw()` | Cache raw HTTP responses instead of DTOs | `false` |
| `postIdempotent()` | Allow POST requests to be cached | `false` |
| `requestCacheTtl(int $seconds)` | Set cache TTL in seconds | `null` (no limit) |
| `requestCacheSize(int $bytes)` | Set max cache entry size | `1MB` |

### Caching Modes

**DTO Caching (default):**
- Caches resolved DTO objects
- Smaller memory footprint
- Faster access to structured data

**RAW Caching:**
- Caches original HTTP response body
- Preserves exact server response
- Useful for debugging or when raw data is needed

### Per-Request Control

Use the `#[Cacheable]` attribute to control caching for specific requests:

```php
use Brahmic\ClientDTO\Attributes\Cacheable;

#[Cacheable(enabled: true, ttl: 7200)]  // Cache for 2 hours
class GetUserRequest extends GetRequest
{
    // This request will be cached regardless of global settings
}

#[Cacheable(enabled: false)]  // Never cache
class CreateUserRequest extends PostRequest
{
    // This request will never be cached
}
```

### Cache Behavior

**Default Behavior:**
- GET requests: Cached if `requestCache()` is enabled
- POST requests: Not cached unless `postIdempotent()` is called
- Cache keys include request class, method, URL, and parameters
- RAW and DTO caches are separate (different cache keys)

**Priority Order:**
1. `#[Cacheable]` attribute on request class
2. `postIdempotent()` setting for POST requests  
3. Global `requestCache()` setting

### Cache Management

```php
// Clear all ClientDTO caches
$client->clearRequestCache();

// Access cached response info
$response = $client->users()->get()->send();
if ($response->getMessage() === 'Successful (cached)') {
    // Response came from cache
}
```
