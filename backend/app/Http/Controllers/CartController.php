<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartValidation;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use \Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function addToCart(CartValidation $request)
    {
        try {
            $checkUser = Gate::denies('checkUser');
            if ($checkUser) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Only users can add to cart'
                ], 403);
            }

            $response = DB::transaction(function () use ($request) {

                $userId = Auth::id();
                $cart = Cart::firstOrCreate([
                    'user_id' => $userId,
                    'status' => 'active',
                ]);

                $totalPrice = 0;
                foreach ($request->items as $item) {

                    $product = Product::where('id', $item['product_id'])
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \Exception('Product not found: ' . $item['product_id']);
                    }

                    if ($product->stock_quantity < $item['quantity']) {
                        throw new \Exception('Insufficient stock for ' . $product->name);
                    }

                    $cartItem = CartItem::where('cart_id', $cart->id)
                        ->where('product_id', $item['product_id'])
                        ->first();

                    if ($cartItem) {
                        $cartItem->quantity += $item['quantity'];
                        $cartItem->save();
                    } else {
                        $cartItem = CartItem::create([
                            'cart_id' => $cart->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                        ]);
                    }
                    $totalPrice += $product->price * $item['quantity'];

                    $product->stock_quantity -= $item['quantity'];
                    $product->save();
                }

                return response()->json([
                    'status' => 1,
                    'message' => 'Product added to cart successfully',
                    'data' => [
                        'items' => $cart->items,
                        'total_price' => $totalPrice
                    ]
                ], 200);

            });

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error adding to cart: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to add product to cart: ',
            ], 500);
        }
    }

    public function viewCart(Request $request)
    {
        try {
            $userId = Auth::id();
            $cart = Cart::where('user_id', $userId)
                ->where('status', 'active')
                ->with('items.product')
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Cart is empty',
                    'data' => [
                        'items' => [],
                        'total_price' => 0
                    ]
                ], 200);
            }

            $totalPrice = $cart->items->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });

            return response()->json([
                'status' => 1,
                'message' => 'Cart retrieved successfully',
                'data' => [
                    'items' => $cart->items,
                    'total_price' => $totalPrice
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error viewing cart: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve cart'
            ], 500);
        }
    }

    public function updateCartItem(CartValidation $request, string $id)
    {
        Gate::authorize('update', Cart::find($id));
        try {
            $response = DB::transaction(function () use ($request, $id) {
                $userId = Auth::id();
                $cart = Cart::where('user_id', $userId)
                    ->where('id', $id)
                    ->where('status', 'active')
                    ->first();

                if (!$cart) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'No active cart found'
                    ], 404);
                }
                foreach ($request->items as $item) {

                    $cartItem = CartItem::where('cart_id', $cart->id)
                        ->where('product_id', $item['product_id'])
                        ->first();

                    if (!$cartItem) {
                        return response()->json([
                            'status' => 0,
                            'message' => 'Cart item not found'
                        ], 404);
                    }

                    $product = Product::find($item['product_id']);
                    if (!$product) {
                        return response()->json([
                            'status' => 0,
                            'message' => 'Product not found'
                        ], 404);
                    }

                    if ($product->stock_quantity < $item['quantity']) {
                        return response()->json([
                            'status' => 0,
                            'message' => 'Insufficient stock for ' . $product->name
                        ], 400);
                    }

                    $quantityDiff = $item['quantity'] - $cartItem->quantity;
                    $product->stock_quantity -= $quantityDiff;
                    $product->save();

                    $cartItem->quantity = $item['quantity'];
                    $cartItem->save();
                }

                $totalPrice = $cart->items->sum(function ($item) {
                    return $item->product->price * $item->quantity;
                });

                return response()->json([
                    'status' => 1,
                    'message' => 'Cart item updated successfully',
                    'data' => [
                        'cart_item' => $cart->items,
                        'total_price' => $totalPrice
                    ]
                ], 200);
            });

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error updating cart item: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to update cart item'
            ], 500);
        }

    }

    public function removeCartItem(Request $request, Cart $cart)
    {
        Gate::authorize('delete', $cart);
        try {
            $userId = Auth::id();
            $cart = Cart::where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No active cart found'
                ], 404);
            }

            foreach ($request->items as $item) {

                $cartItem = CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$cartItem) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Cart item not found'
                    ], 404);
                }
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->stock_quantity += $cartItem->quantity;
                    $product->save();
                }

                $cartItem->delete();
            }
            $cart->delete();

            return response()->json([
                'status' => 1,
                'message' => 'Cart item removed successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error removing cart item: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to remove cart item'
            ], 500);
        }
    }

    public function checkout(Request $request)
    {
        try{

            $userId = Auth::id();
            $cart = Cart::where('user_id', $userId)
                ->where('status', 'active')
                ->with('items.product')
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No items in cart to checkout'
                ], 400);
            }

            $cart->status = 'checked_out';
            $cart->save();

            return response()->json([
                'status' => 1,
                'message' => 'Checkout successful',
                'data' => [
                    'cart_id' => $cart->id,
                    'items' => $cart->items,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error during checkout: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Checkout failed'
            ], 500);
        }
    }

    public function viewCheckedOut(Request $request)
    {
        try {
            $userId = Auth::id();
            $carts = Cart::where('user_id', $userId)
                ->where('status', 'checked_out')
                ->with('items.product')
                ->get();

            if ($carts->isEmpty()) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No checked out carts found',
                ], 200);
            }

            return response()->json([
                'status' => 1,
                'data' => ['carts' => $carts]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error viewing checked out carts: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve checked out carts'
            ], 500);
        }
    }

}
