<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {

            $perPage = $request->input('per_page', 5);
            $products = Product::where('deleted_at', null)->paginate($perPage);

            return response()->json(['status' => 1, 'data' => ['products' => $products]]);
        } catch (\Exception $e) {
            \Log::error('Error fetching products: ' . $e->getMessage());
            return response()->json(['status' => 1, 'message' => 'Failed to fetch products']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            if (!Gate::allows('checkAdmin')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized to create product'
                ], 403);
            }
            $product = Product::create($request->all());

            return response()->json([
                'status' => 1,
                'message' => 'Product created successfully',
                'data' => ['product' => $product]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating product: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to create product'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => 0,
                'message' => 'Product not found'
            ], 404);
        }
        return response()->json([
            'status' => 1,
            'data' => ['product' => $product]
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            if (!Gate::allows('checkAdmin')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized to update product'
                ], 403);
            }
            // checking product
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Product not found'
                ], 404);
            }

            $product->update($request->all());

            return response()->json([
                'status' => 1,
                'message' => 'Product updated successfully',
                'data' => ['product' => $product]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating product: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to update product'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            if (!Gate::allows('checkAdmin')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized to delete product'
                ], 403);
            }

            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Product not found'
                ], 404);
            }

            $product->update([
                'deleted_at' => now()
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to delete product'
            ], 500);
        }
    }
}
