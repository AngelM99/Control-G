<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('nombre')->get();
        return response()->json($categories);
    }

    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categories,nombre',
        ]);
        
        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    public function update(\Illuminate\Http\Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categories,nombre,' . $category->id,
        ]);
        
        $category->update($validated);
        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        // For simplicity, we just delete it. In a real app we might soft delete or check if it's in use.
        $category->delete();
        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
