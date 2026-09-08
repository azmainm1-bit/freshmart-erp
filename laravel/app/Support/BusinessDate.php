<?php

namespace App\Support;

final class BusinessDate
{
    public static function today(): string
    {
        return now(config('erp.timezone'))->format('Y-m-d');
    }
}
