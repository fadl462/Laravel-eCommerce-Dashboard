<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected InventoryService $inventory,
    ) {
    }

    /**
     * Backs the Products list page: search, category/status/stock filters, and
     * sorting are all real query constraints here (the prototype's UI for
     * these existed before the backend did — this is what actually wires it up).
     */
    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'images' => fn ($q) => $q->where('is_primary', true)]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($stock = $request->query('stock_status')) {
            match ($stock) {
                'out' => $query->where('stock_quantity', '<=', 0),
                'low' => $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0),
                'in_stock' => $query->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
                default => null,
            };
        }

        $sort = $request->query('sort', '-created_at'); // e.g. "price" or "-price"
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (in_array($column, ['name', 'regular_price', 'stock_quantity', 'created_at'], true)) {
            $query->orderBy($column, $direction);
        }

        $products = $query->paginate($request->integer('per_page', 20));

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'images', 'variations']);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);

        $product = Product::create($data);

        foreach ($request->input('variations', []) as $variation) {
            $product->variations()->create($variation);
        }

        if ($product->track_inventory && $product->stock_quantity > 0) {
            $this->inventory->manualAdjustment($product, $product->stock_quantity, $request->user(), 'Initial stock on creation');
        }

        $this->activityLogger->log($request->user(), 'Created product', 'Products', $product, $product->name);

        return (new ProductResource($product->fresh(['category', 'images', 'variations'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());
        $this->activityLogger->log($request->user(), 'Updated product', 'Products', $product, $product->name);

        return new ProductResource($product->fresh(['category', 'images', 'variations']));
    }

    public function destroy(Request $request, Product $product)
    {
        abort_unless($request->user()->hasPermission('products.delete'), 403);

        $name = $product->name;
        $product->delete(); // soft delete — recoverable, and keeps historic order_items intact

        $this->activityLogger->log($request->user(), 'Deleted product', 'Products', null, $name);

        return response()->json(['message' => 'Product deleted.']);
    }

    /** Manual stock adjustment (+/-), separate from the automatic order-driven movements. */
    public function adjustStock(Request $request, Product $product)
    {
        abort_unless($request->user()->hasPermission('products.edit'), 403);

        $data = $request->validate([
            'change' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = $this->inventory->manualAdjustment($product, $data['change'], $request->user(), $data['note'] ?? null);
        $this->activityLogger->log($request->user(), 'Adjusted stock', 'Inventory', $product, $product->name, $data);

        return new ProductResource($product);
    }
}
