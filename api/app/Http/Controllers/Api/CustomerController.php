<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function index(Request $request)
    {
        $query = Customer::query()->withCount('orders');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $customers = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer)
    {
        $customer->loadCount('orders')->load('addresses');

        return new CustomerResource($customer);
    }

    public function orders(Customer $customer)
    {
        $orders = $customer->orders()->with('items')->orderByDesc('created_at')->paginate(10);

        return OrderResource::collection($orders);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated() + ['status' => 'active']);
        $this->activityLogger->log($request->user(), 'Created customer', 'Customers', $customer, $customer->name);

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($request->user()->hasPermission('customers.edit'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        $customer->update($data);
        $this->activityLogger->log($request->user(), 'Updated customer', 'Customers', $customer, $customer->name);

        return new CustomerResource($customer);
    }

    public function setStatus(Request $request, Customer $customer)
    {
        abort_unless($request->user()->hasPermission('customers.edit'), 403);

        $data = $request->validate(['status' => ['required', 'in:active,blocked']]);
        $customer->update($data);

        $this->activityLogger->log(
            $request->user(),
            $data['status'] === 'blocked' ? 'Disabled customer account' : 'Re-enabled customer account',
            'Customers',
            $customer,
            $customer->name
        );

        return new CustomerResource($customer);
    }

    public function destroy(Request $request, Customer $customer)
    {
        abort_unless($request->user()->hasPermission('customers.delete'), 403);

        $name = $customer->name;
        $customer->delete();
        $this->activityLogger->log($request->user(), 'Deleted customer', 'Customers', null, $name);

        return response()->json(['message' => 'Customer deleted.']);
    }
}
