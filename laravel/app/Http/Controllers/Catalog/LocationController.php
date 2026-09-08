<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('locations.manage');

        return Inertia::render('catalog/locations/index', [
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return back()->with('success', 'Location created.');
    }
}
