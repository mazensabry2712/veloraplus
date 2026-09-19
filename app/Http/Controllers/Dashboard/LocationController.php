<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\LocationManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreLocationRequest;
use App\Http\Requests\Dashboard\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class LocationController extends Controller
{
    public function store(StoreLocationRequest $request, LocationManager $manager): RedirectResponse
    {
        Gate::authorize('create', Location::class);

        $manager->create($request->validated());

        return to_route('dashboard')->with('status', 'Location created successfully.');
    }

    public function update(
        UpdateLocationRequest $request,
        Location $location,
        LocationManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $location);

        $manager->update($location, $request->validated());

        return to_route('dashboard')->with('status', 'Location updated successfully.');
    }

    public function destroy(Location $location, LocationManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $location);

        $manager->archive($location);

        return to_route('dashboard')->with('status', 'Location archived successfully.');
    }
}
