<?php

namespace Brahmic\ClientDTO\Enums;

enum PaginatedStrategy
{
    case All;
    case Range;
    case Pages;
    case Number;
}
