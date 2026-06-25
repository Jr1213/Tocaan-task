<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed the products table.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Mechanical Keyboard', 'price' => 79.99, 'quantity' => 50],
            ['name' => 'Wireless Mouse',      'price' => 29.50, 'quantity' => 80],
            ['name' => '27" Monitor',         'price' => 249.00, 'quantity' => 25],
            ['name' => 'USB-C Hub',           'price' => 39.99, 'quantity' => 100],
            ['name' => 'Laptop Stand',        'price' => 45.00, 'quantity' => 60],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
