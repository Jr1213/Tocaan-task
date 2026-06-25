<?php

namespace App\Http\Controllers\API;

use App\Actions\CreateOrderAction;
use App\Actions\DeleteOrderAction;
use App\Actions\UpdateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Service\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private CreateOrderAction $createOrderAction,
        private UpdateOrderAction $updateOrderAction,
        private DeleteOrderAction $deleteOrderAction,
    ) {}

    public function index(): JsonResponse
    {
        $orders = $this->orderService->paginateForUser(request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data'    => OrderResource::collection($orders),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $data['user_id'] = $request->user()->id;

        $order = $this->createOrderAction->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data'    => new OrderResource($order),
        ], Response::HTTP_CREATED);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::forUser(request()->user())->authorize('view', $order);

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully.',
            'data'    => new OrderResource($order->load('items')),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $order);

        $order = $this->updateOrderAction->execute($order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully.',
            'data'    => new OrderResource($order),
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        Gate::forUser(request()->user())->authorize('delete', $order);

        $this->deleteOrderAction->execute($order);

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully.',
        ]);
    }
}
