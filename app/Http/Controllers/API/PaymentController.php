<?php

namespace App\Http\Controllers\API;

use App\Actions\PayOrderAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(private PayOrderAction $payOrderAction) {}

    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        Gate::forUser($request->user())->authorize('pay', $order);

        $method  = PaymentMethod::from($request->validated('payment_method'));
        $payment = $this->payOrderAction->execute($order, $method);

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'data'    => new PaymentResource($payment),
        ], Response::HTTP_CREATED);
    }
}
