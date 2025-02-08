## Создание запросов

## Установка параметров запроса

#### Способ 1

```php
        $request->set(regions: [], lastName: 'Ivanov', firstName: 'Ivan');       
```

#### Способ 2

```php

        $request->lastName = 'Ivanov';
        $request->firstName = 'Ivan';
        $request->regions = [1,2,3];
```

#### Способ 3

```php
    $request->setFrom(['firstName' => 'Ivan']);        
```

#### Способ 4

```php 
    $personalData = new PersonalData(regions: [1,2], lastName: 'Ivanov', firstName: 'Ivan');
    $request->setFrom($personalData);
```
