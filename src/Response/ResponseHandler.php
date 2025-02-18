<?php

namespace Brahmic\ClientDTO\Response;

use Illuminate\Http\Client\Response;

class ResponseHandler
{
    const array MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'text/plain',
        'text/csv',
        'application/pdf',
        'application/zip',
        'application/msword',
        'application/vnd.ms-excel',
        'application/x-x509-ca-cert',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream',
        'application/pgp-signature',
    ];

    public function handle(Response $response)
    {
        if ($response->successful()) {
            //2xx

            if ($this->hasFile($response)) {
                // вернуть файл
            } else {

            }

            $data = $response->json();


        } elseif ($response->clientError()) {
            //4xx
            throw new Exception('Ошибка клиента');


        } elseif ($response->serverError()) {
            //5xx

            throw new Exception('Ошибка сервера');

        } else {

            throw new Exception('Неожиданный статус');
        }

        //Если ошибка техническая, возвращаем типовой ответ
        //Если ошибка от валидатора клиента — возвращаем её
        return $response;
    }

    private function hasFile(Response $response): bool
    {
        $hasContentType = null;

        if ($contentType = $response->header('Content-Type')) {
            $hasContentType = array_find(self::MIME_TYPES, function ($type) use ($contentType) {
                return $type === $contentType;
            });
        };

        $hasContentDisposition = str_contains($response->header('Content-Disposition'), 'attachment');

        return $hasContentType || $hasContentDisposition;
    }
}