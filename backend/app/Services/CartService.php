<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;


class CartService
{
    public function storeCartItems(Cart $cart, array $items): void
    {
        foreach ($items as $item) {
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $item['product_id'])
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $item['quantity'];
                $cartItem->save();
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }
    }
}