<?php

namespace OptiGov\FitConnect\Facades;

use Illuminate\Support\Facades\Facade;
use OptiGov\FitConnect\FitConnect\Client;

class FitConnect extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
