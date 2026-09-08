<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class DocumentNumber
{
    public static function next(string $prefix): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Document numbering requires a transaction.');
        }
        $row = DB::selectOne('insert into document_sequences (prefix, value) values (?, 1) on conflict (prefix) do update set value = document_sequences.value + 1 returning value', [$prefix]);

        return $prefix.'-'.str_pad((string) $row->value, 8, '0', STR_PAD_LEFT);
    }
}
