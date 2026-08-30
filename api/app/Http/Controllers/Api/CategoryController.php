<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->withCount('products')
            ->with(['children' => fn ($q) => $q->withCount('products')])
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('products.create'), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
        $category = Category::create($data);

        $this->activityLogger->log($request->user(), 'Created category', 'Categories', $category, $category->name);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($request->user()->hasPermission('products.edit'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $category->update($data);
        $this->activityLogger->log($request->user(), 'Updated category', 'Categories', $category, $category->name);

        return new CategoryResource($category);
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless($request->user()->hasPermission('products.delete'), 403);

        if ($category->products()->exists() || $category->children()->exists()) {
            abort(422, 'Cannot delete a category that still has products or subcategories.');
        }

        $name = $category->name;
        $category->delete();
        $this->activityLogger->log($request->user(), 'Deleted category', 'Categories', null, $name);

        return response()->json(['message' => 'Category deleted.']);
    }
}
