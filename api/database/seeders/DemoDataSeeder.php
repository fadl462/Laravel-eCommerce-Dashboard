<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Optional — populates enough data to click through the dashboard locally.
 * Not run by default; enable the line in DatabaseSeeder::run() to use it.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::create(['name' => 'Electronics', 'name_ar' => 'إلكترونيات', 'slug' => 'electronics']);
        $laptops = Category::create(['name' => 'Laptops', 'parent_id' => $electronics->id, 'slug' => 'laptops']);
        $accessories = Category::create(['name' => 'Accessories', 'parent_id' => $electronics->id, 'slug' => 'accessories']);

        $laptop = Product::create([
            'category_id' => $laptops->id,
            'name' => 'Laptop Pro 15" 2026',
            'slug' => 'laptop-pro-15-2026-'.Str::random(4),
            'sku' => 'LP001',
            'product_type' => 'simple',
            'regular_price' => 1200,
            'stock_quantity' => 42,
            'low_stock_threshold' => 15,
            'status' => 'active',
        ]);

        $mouse = Product::create([
            'category_id' => $accessories->id,
            'name' => 'Ergonomic Mouse',
            'slug' => 'ergonomic-mouse-'.Str::random(4),
            'sku' => 'MS004',
            'product_type' => 'simple',
            'regular_price' => 45,
            'stock_quantity' => 0,
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'name' => 'Sarah Ahmed',
            'email' => 'sarah@example.com',
            'phone' => '+000 000 0000',
            'country' => 'Ghana',
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-1025',
            'customer_id' => $customer->id,
            'subtotal' => 1250,
            'shipping_amount' => 25,
            'tax_amount' => 80,
            'discount_amount' => 50,
            'total' => 1305,
            'payment_status' => 'paid',
            'status' => 'processing',
            'shipping_address_line1' => '24 Independence Ave',
            'shipping_city' => 'Accra',
            'shipping_country' => 'Ghana',
        ]);

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $laptop->id,
            'product_name' => $laptop->name, 'sku' => $laptop->sku,
            'price' => 1000, 'quantity' => 1, 'total' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $mouse->id,
            'product_name' => $mouse->name, 'sku' => $mouse->sku,
            'price' => 125, 'quantity' => 2, 'total' => 250,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'amount' => 1305,
            'status' => 'paid',
            'transaction_reference' => 'pi_demo_'.Str::random(10),
            'paid_at' => now(),
        ]);
    }
}
