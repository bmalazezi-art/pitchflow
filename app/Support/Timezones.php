<?php

namespace App\Support;

final class Timezones
{
    public static function resolve(string $timezone): string
    {
        return $timezone === 'Europe/Pristina' ? 'Europe/Belgrade' : $timezone;
    }
}
