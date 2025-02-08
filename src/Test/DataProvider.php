<?php

namespace Brahmic\ClientDTO\Test;

use Brahmic\ClientDTO\DataProviderClient;
use GuzzleHttp\RequestOptions;

class DataProvider extends DataProviderClient
{

    // Системные запросы (получение uuid, прочее)
    public function __construct(array $config = [])
    {

        $this
            ->setBaseUrl('https://example.com/services/')
            ->setTimeout(60)
            ->setHeaders([])
            ->setDebug(true)
            ->setRequestBodyType(RequestOptions::MULTIPART)
            ->addQueryParam('token', '45fadabbd2113da853324e3b6c8b4927');


        $createUuidRequest = $this->person()
            //->createUuid(new PersonalData(regions: [121,565], lastName: 'PersonalData asd', firstName: 'PersonalData asd'));
            ->createUuid();

        $createUuidRequest->set(regions: [1], lastName: 'Ivanov', firstName: 'Ivan');

//        $createUuidRequest->regions = [1,2,3];
//        $createUuidRequest->lastName = 'Ivanov';
//        $createUuidRequest->firstName = 'Ivan';
//
//        $createUuidRequest->setFrom(['firstName' => 'Ivan']);


        $createUuidRequest->setTimeout(30);

        //dump($createUuidRequest->getQueryParams());
        dump($createUuidRequest->getTimeout());
        dump($createUuidRequest->getUrl());
        dump($createUuidRequest->getUri());
        dump($createUuidRequest->getRequestBodyType());
        dump($createUuidRequest->resolveDtoClass());
        dump('======================================');

        $createUuidRequest->send();


        dd($createUuidRequest);
    }
}