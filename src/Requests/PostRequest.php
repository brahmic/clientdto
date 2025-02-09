<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequestBuilder;

class PostRequest extends AbstractRequestBuilder
{

    final public function getBodyParams(): array
    {
        if ($this->isQueryParamsOverride()) {
            $paramsFromProperties = array_diff_key($this->bodyParams(), $this->queryParams());
        } else {
            $paramsFromProperties = $this->bodyParams();
        }

        return array_merge(
            $paramsFromProperties,
        );
    }

    protected function bodyParams(): array
    {
        return $this->getParamsFromProperties()->toArray();
    }
}
