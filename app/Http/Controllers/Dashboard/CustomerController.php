<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\CustomerManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreCustomerRequest;
use App\Http\Requests\Dashboard\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function index(): View
    {
        Gate::authorize('customers.view');

        return view('dashboard.company.customers', [
            'customers' => Customer::query()->latest()->paginate(20),
        ]);
    }


    public function store(StoreCustomerRequest $request, CustomerManager $manager): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $manager->create($request->validated());

        return to_route('dashboard')->with('status', 'Customer created successfully.');
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        CustomerManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $customer);

        $manager->update($customer, $request->validated());

        return to_route('dashboard')->with('status', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer, CustomerManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $manager->archive($customer);

        return to_route('dashboard')->with('status', 'Customer archived successfully.');
    }
}
