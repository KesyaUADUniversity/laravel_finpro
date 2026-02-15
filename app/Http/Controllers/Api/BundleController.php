<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $bundles = Bundle::with(['products' => function ($query) {
                $query->select('id', 'name');
            }])
            ->where('is_active', true)
            ->get()
            ->map(function ($bundle) {
                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'description' => $bundle->description,
                    'total_price' => (int) $bundle->total_price,
                    'original_price' => (int) $bundle->original_price,
                    'savings' => (int) $bundle->savings,
                    'items' => $bundle->products->map(function ($product) use ($bundle) {
                        $pivot = $bundle->products->firstWhere('id', $product->id)?->pivot;
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'quantity' => $pivot ? (int) $pivot->quantity : 1
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Bundles retrieved successfully',
                'data' => $bundles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bundles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'total_price' => 'required|integer|min:0',
                'original_price' => 'required|integer|min:0',
                'savings' => 'required|integer|min:0'
            ]);

            $bundle = Bundle::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Bundle created successfully',
                'data' => $bundle
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bundle',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}