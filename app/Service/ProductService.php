<?php

namespace App\Service;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Paginate all products, newest first.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()->latest()->paginate($perPage);
    }

    /**
     * Load the product together with every order that contains it.
     */
    public function loadWithOrders(Product $product): Product
    {
        return $product->load('orders.items');
    }
}
