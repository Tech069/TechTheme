<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\DGEN\Game;
use Pterodactyl\Models\DGEN\GameCategory;

class StoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $categories = GameCategory::with('games')->orderBy('position')->get();
            return response()->json(['categories' => $categories]);
        } catch (\Exception $e) {
            return response()->json(['categories' => []], 500);
        }
    }

    public function products(Request $request): JsonResponse
    {
        try {
            $categoryId = $request->input('category_id');

            $query = Game::where('available', true);
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $products = $query->get(['id', 'name', 'slug', 'icon', 'category_id']);

            return response()->json(['products' => $products]);
        } catch (\Exception $e) {
            return response()->json(['products' => []], 500);
        }
    }

    public function categories(Request $request): JsonResponse
    {
        try {
            $categories = GameCategory::orderBy('position')->get(['id', 'name', 'slug', 'icon']);
            return response()->json(['categories' => $categories]);
        } catch (\Exception $e) {
            return response()->json(['categories' => []], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:1']);

        try {
            $query = $request->input('query');
            $products = Game::where('name', 'LIKE', "%$query%")
                ->where('available', true)
                ->get(['id', 'name', 'slug', 'icon']);

            return response()->json(['products' => $products]);
        } catch (\Exception $e) {
            return response()->json(['products' => []], 500);
        }
    }
}
