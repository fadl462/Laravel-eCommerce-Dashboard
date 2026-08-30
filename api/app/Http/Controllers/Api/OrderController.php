<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected ActivityLogger $activityLogger,
    ) {
    }

    public function index(Request $request)
    {
        $query = Order::query()->with('customer')->withCount('items');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $orders = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items.product', 'payment', 'statusHistories.changedBy', 'shipment']);

        return new OrderResource($order);
    }

    /** Fulfillment status update — validated against the allowed pipeline transitions. */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $order = $this->orderService->transitionStatus(
            $order,
            $request->validated('status'),
            $request->user(),
            $request->validated('note')
        );

        return new OrderResource($order->load(['customer', 'items', 'payment']));
    }

    public function cancel(Request $request, Order $order)
    {
        abort_unless($request->user()->hasPermission('orders.cancel'), 403);

        $order = $this->orderService->transitionStatus($order, 'cancelled', $request->user(), $request->input('reason'));

        return new OrderResource($order);
    }

    /** Order Activity timeline shown on the Order Detail page. */
    public function activity(Order $order)
    {
        $history = $order->statusHistories()->with('changedBy')->orderByDesc('created_at')->get();

        return response()->json($history->map(fn ($h) => [
            'field' => $h->field,
            'from' => $h->from_value,
            'to' => $h->to_value,
            'note' => $h->note,
            'changed_by' => $h->changedBy?->name ?? 'System',
            'at' => $h->created_at->toIso8601String(),
        ]));
    }
}
