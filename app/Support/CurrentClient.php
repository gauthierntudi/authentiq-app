<?php

namespace App\Support;

use App\Models\Client;

class CurrentClient
{
    private static ?Client $client = null;

    public static function set(?Client $client): void
    {
        self::$client = $client;
    }

    public static function get(): ?Client
    {
        return self::$client;
    }
}
