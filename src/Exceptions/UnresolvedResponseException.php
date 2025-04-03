<?php

namespace Brahmic\ClientDTO\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class UnresolvedResponseException extends Exception
{

    public function __construct($message, protected Response $response)
    {
        parent::__construct($message, 502);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
