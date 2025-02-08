## GetRequestBuilder

#### Что делает этот класс?

- ✅ Позволяет гибко собирать GET-запрос
- ✅ Поддерживает Query-параметры, Headers и Cookies
- ✅ Использует Laravel HTTP Client (Http::get())
- ✅ Поддерживает цепочечный вызов методов
- ✅ Упрощает работу с API

### Простой `GET` запрос

```php
$response = (new GetRequestBuilder('https://api.example.com/users'))
    ->send();
```

### GET-запрос с `Query` параметрами

```php
$response = (new GetRequestBuilder('https://api.example.com/users'))
    ->withQuery(['page' => 2, 'limit' => 10])
    ->send();
```

### GET-запрос с заголовками

```php
$response = (new GetRequestBuilder('https://api.example.com/profile'))
    ->withHeaders([
        'Authorization' => 'Bearer abc123',
        'Accept' => 'application/json'
    ])
    ->send();

# Добавит заголовки Authorization и Accept
```

### GET-запрос с `Cookie`

```php
$response = (new GetRequestBuilder('https://api.example.com/orders'))
    ->withCookies(['session_id' => 'xyz123'])
    ->send();
```

### GET-запрос с комбинацией параметров (`Query` параметрами, заголовками и `Cookie`)

```php
$response = (new GetRequestBuilder('https://api.example.com/products'))
    ->withQuery(['category' => 'electronics'])
    ->withHeaders(['Authorization' => 'Bearer abc123'])
    ->withCookies(['session_id' => 'xyz123'])
    ->send();
```

## PostRequestBuilder

#### Что делает этот класс?

- ✅ Позволяет гибко собирать POST-запрос
- ✅ Поддерживает все возможные типы данных
- ✅ Использует Laravel HTTP Client (Http::post())
- ✅ Позволяет цепочечный вызов методов
- ✅ Удобно работает с файлами

### Отправка `JSON` 

```php
$response = (new PostRequestBuilder('https://api.example.com/users'))
    ->withQuery(['debug' => true])
    ->withHeaders(['Authorization' => 'Bearer abc123'])
    ->withBody([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ])
    ->send();
```

### Отправка `x-www-form-urlencoded` 

```php
$response = (new PostRequestBuilder('https://api.example.com/login'))
    ->withBody([
        'username' => 'john',
        'password' => 'secret'
    ], 'form')
    ->send();
```

### Отправка `multipart/form-data` (файлы)

```php
$response = (new PostRequestBuilder('https://api.example.com/upload'))
    ->withFiles([
        'avatar' => storage_path('app/public/avatar.jpg')
    ])
    ->withBody([
        'username' => 'john_doe'
    ])
    ->send();
```

### Отправка запроса с `Query` параметрами и `Cookies`

```php

$response = (new PostRequestBuilder('https://api.example.com/orders'))
    ->withQuery(['include' => 'items'])
    ->withCookies(['session_id' => 'xyz123'])
    ->withBody([
        'product_id' => 42,
        'quantity' => 3
    ])
    ->send();



```
