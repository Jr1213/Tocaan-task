<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'price', 'quantity'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price'    => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /**
     * The order line items that reference this product.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The orders that contain this product, resolved through its line items.
     *
     * @return HasManyThrough<Order, OrderItem, $this>
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            OrderItem::class,
            'product_id', // FK on order_items pointing to products
            'id',         // local key on orders
            'id',         // local key on products
            'order_id',   // FK on order_items pointing to orders
        )->distinct();
    }
}
