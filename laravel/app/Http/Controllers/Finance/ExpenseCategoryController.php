<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Support\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('finance.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:expense_categories,name']]);
        DB::transaction(function () use ($request, $data) {
            $category = ExpenseCategory::create($data);
            AuditLog::record($request->user(), 'EXPENSE_CATEGORY_CREATED', $category);
        });

        return back()->with('success', 'Expense category created.');
    }
}
