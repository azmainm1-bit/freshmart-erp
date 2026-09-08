<?php

namespace App\Support\Inventory;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Support\Purchasing\ReceivingService;

class GoodsReceiptService
{
    public static function post(User $actor, array $input): GoodsReceipt
    {
        return ReceivingService::post($actor, $input);
    }
}
