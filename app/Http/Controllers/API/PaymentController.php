<?php

namespace App\Http\Controllers\API;

use App\Actions\PayOrderAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Service\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PayOrderAction $payOrderAction,
    ) {}

    /**
     * List all payments across the authenticated user's orders.
     */
    public function index(): JsonResponse
    {
        $payments = $this->paymentService->paginateForUser(request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully.',
            'data' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * Show the payment for a specific order.
     */
    public function show(Order $order): JsonResponse
    {
        Gate::forUser(request()->user())->authorize('view', $order);

        $payment = $order->payment()->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully.',
            'data' => new PaymentResource($payment),
        ]);
    }

    /**
     * Process a payment for an order.
     */
    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        Gate::forUser($request->user())->authorize('pay', $order);

        $method = PaymentMethod::from($request->validated('payment_method'));
        $payment = $this->payOrderAction->execute($order, $method);

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'data' => new PaymentResource($payment),
        ], Response::HTTP_CREATED);
    }
}
