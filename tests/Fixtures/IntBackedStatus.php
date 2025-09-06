<?php declare(strict_types = 1);

namespace Tests\Fixtures;

enum IntBackedStatus: int
{
    case Active = 1;
    case Inactive = 0;
    case Pending = 2;
}