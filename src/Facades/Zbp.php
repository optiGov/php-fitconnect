<?php

namespace OptiGov\FitConnect\Facades;

use Illuminate\Support\Facades\Facade;
use OptiGov\FitConnect\Zbp\Client;

class Zbp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
