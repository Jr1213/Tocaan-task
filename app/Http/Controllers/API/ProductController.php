<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Service\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(): JsonResponse
    {
        $products = $this->productService->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data'    => ProductResource::collection($products),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product = $this->productService->loadWithOrders($product);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data'    => new ProductResource($product),
        ]);
    }
}
