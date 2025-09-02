## ClientDTO

#### What does it do?

- ✅ Integration with external API
- ✅ Error and state handling, always predictable response
- ✅ Request grouping
- ✅ Working with files and archives
- ✅ Support for queries with pagination
- ✅ Additional query attempts with flexible configuration
- ✅ **HTTP Request Caching** - automatic caching of successful requests
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

### HTTP Request Caching ⚡

Automatic caching of successful HTTP requests with flexible control levels:

```php
class CustomClient extends ClientDTO
{
    public function __construct(array $config = [])
    {
        $this
            ->setBaseUrl('https://api.example.com/')
            ->cache(true)           // Metadata caching (ResourceMap)
            ->cacheRequests(true)   // 🆕 HTTP request caching
            ->cacheTtl(3600)        // 🆕 Default TTL: 1 hour
            ->setTimeout(60);
    }
}

// Basic usage - automatic caching
$users1 = $client->users()->list()->set(page: 1)->send()->resolved(); // HTTP request + cache
$users2 = $client->users()->list()->set(page: 1)->send()->resolved(); // From cache ⚡
$users3 = $client->users()->list()->set(page: 2)->send()->resolved(); // New HTTP request

// Instance-level control
$freshData = $client->users()->show()
    ->set(id: '123')
    ->skipCache()           // 🆕 Skip cache (fresh HTTP request)
    ->send()->resolved();

$forcedCache = $client->users()->show()
    ->set(id: '123') 
    ->skipCache(false)      // 🆕 Force cache update (HTTP request + cache)
    ->send()->resolved();

// Cache management
$client->clearRequestCache();                    // Clear all request cache
$client->clearRequestCacheByTags(['users']);    // Clear by tags
```

### Custom client example

```php
class CustomClient extends ClientDTO
{

    public function __construct(array $config = [])
    {
        $this
            ->cache(false)                  // Metadata caching disabled
            ->cacheRequests(true)           // 🆕 HTTP request caching enabled
            ->cacheTtl(1800)                // 🆕 Default TTL: 30 minutes
            ->setBaseUrl('https://customapiservice.com/')
            ->setTimeout(60)
            ->onClearCache(function () {
                $this->report()->clearCache();
                $this->clearRequestCache();  // 🆕 Also clear HTTP request cache
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
use Brahmic\ClientDTO\Attributes\Cacheable;
use Brahmic\ClientDTO\Contracts\CacheableRequestInterface;

// Declarative caching with attributes
#[Cacheable(ttl: 1800, tags: ['children', 'person'], enabled: true)]
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

// Programmatic caching control
class PersonProfile extends GetRequest implements CacheableRequestInterface
{
    public const string URI = 'person/{uuid}/profile';
    protected ?string $dto = PersonProfileDto::class;

    public string $uuid;
    public ?bool $include_details = false;

    public function set(string $uuid, ?bool $include_details = false): static
    {
        return $this->assignSetValues();
    }

    // 🆕 Programmatic cache control
    public function getCacheTtl(): ?int
    {
        return $this->include_details ? 300 : 3600; // 5 min vs 1 hour
    }

    public function getCacheTags(): array
    {
        return ['person', "person:{$this->uuid}"];
    }

    public function shouldCache(mixed $resolved): bool
    {
        // Only cache if person is active
        return $resolved instanceof PersonProfileDto && $resolved->is_active;
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

    protected ?string $dto = FileResponse::class; // 📝 FileResponse is NOT cached by default

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

### Caching Priorities 🎯

Cache control follows this priority order (highest to lowest):

1. **Instance level**: `->skipCache()` / `->skipCache(false)`
2. **Class level**: `#[Cacheable(enabled: true/false)]` attribute  
3. **Client level**: `->cacheTtl(seconds)` for default TTL
4. **Global level**: `->cacheRequests(true/false)`

### TTL Priority (highest to lowest):

1. **Method level**: `getCacheTtl()` in request (programmatic)
2. **Class level**: `#[Cacheable(ttl: seconds)]` attribute
3. **Client level**: `->cacheTtl(seconds)` 🆕
4. **Config level**: `CacheConfig::getDefaultTtl()`

```php
// Examples of priority in action:
$client->cacheRequests(false);  // Globally disabled

// But this request will update cache (skipCache(false) forces HTTP + cache)
$data = $client->users()->show()->set(id: '123')->skipCache(false)->send()->resolved();

// This request will never be cached (highest priority)
$fresh = $client->users()->show()->set(id: '123')->skipCache()->send()->resolved();
```
