<?php

namespace App\Console\Commands;

use App\Support\Reporting\Reconciliation;
use Illuminate\Console\Command;

class ReconcileErpCommand extends Command
{
    protected $signature = 'erp:reconcile';

    protected $description = 'Read-only reconciliation of inventory, sales, payments, returns and closed tills';

    public function handle(): int
    {
        $checks = Reconciliation::check();
        $this->table(['Integrity check', 'Mismatched records'], collect($checks)->map(fn ($count, $name) => [$name, $count])->values()->all());

        return array_sum($checks) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
