<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Http\Request;
use App\Services\OngkirService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Http\Resources\CartItemResource;

class CartController extends Controller
{
    protected $ongkirService;

    public function __construct(OngkirService $ongkirService)
    {
        $this->ongkirService = $ongkirService;
    }

    public function index(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        // Get all carts for customer
        $carts = Cart::where('customer_id', $customer->id)
                    ->with(['umkm', 'cartItems.product.umkm', 'cartItems.product.category'])
                    ->get();

        // Group by UMKM
        $groupedCart = [
            'carts' => $carts,
            'total_items' => $carts->sum(fn($cart) => $cart->cartItems->sum('quantity')),
            'total_price' => $carts->sum(fn($cart) => $cart->cartItems->sum(fn($item) => $item->product->harga * $item->quantity))
        ];

        return response()->json([
            'success' => true,
            'data' => $groupedCart
        ]);
    }

    public function addToCart(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::with('umkm')->findOrFail($request->product_id);

        if ($product->stok < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient stock',
                'available_stock' => $product->stok
            ], 422);
        }

        // Find or create cart for this UMKM
        $cart = Cart::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'umkm_id' => $product->umkm_id
            ]
        );

        // Add or update cart item
        $cartItem = CartItem::where('cart_id', $cart->id)
                          ->where('product_id', $request->product_id)
                          ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;

            if ($product->stok < $newQuantity) {
                return response()->json([
                    'message' => 'Insufficient stock for additional quantity',
                    'available_stock' => $product->stok,
                    'current_quantity' => $cartItem->quantity
                ], 422);
            }

            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart',
            'data' => $cart->load(['cartItems.product.umkm'])
        ]);
    }

    public function updateCart(Request $request, CartItem $cartItem)
    {
        $customer = $request->user()->customer;

        if (!$customer || $cartItem->cart->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($cartItem->product->stok < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient stock',
                'available_stock' => $cartItem->product->stok
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully',
            'data' => $cartItem->load(['product.umkm'])
        ]);
    }

    public function removeFromCart(Request $request, CartItem $cartItem)
    {
        $customer = $request->user()->customer;

        if (!$customer || $cartItem->cart->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cartItem->delete();

        // Delete cart if no items left
        if ($cartItem->cart->cartItems()->count() === 0) {
            $cartItem->cart->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    public function clearCart(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        Cart::where('customer_id', $customer->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    }

    public function calculateShipping(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $request->validate([
            'umkm_ids' => 'required|array',
            'umkm_ids.*' => 'exists:umkms,id'
        ]);

        $shippingCosts = [];
        $totalShipping = 0;

        foreach ($request->umkm_ids as $umkmId) {
            $umkm = \App\Models\Umkm::find($umkmId);

            if ($umkm && $customer->kecamatan) {
                $ongkir = $this->ongkirService->calculateOngkir($umkm, $customer);
                $shippingCosts[$umkmId] = $ongkir;
                $totalShipping += $ongkir['tarif'];
            } else {
                $shippingCosts[$umkmId] = [
                    'tarif' => 15000,
                    'estimasi_hari' => 3,
                    'keterangan' => 'Default shipping cost',
                    'jarak_km' => null
                ];
                $totalShipping += 15000;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'shipping_costs' => $shippingCosts,
                'total_shipping' => $totalShipping
            ]
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.cart_item_id' => 'required|exists:cart_items,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        $updatedItems = [];

        foreach ($request->items as $itemData) {
            $cartItem = CartItem::find($itemData['cart_item_id']);

            if ($cartItem && $cartItem->cart->customer_id === $customer->id) {
                if ($cartItem->product->stok < $itemData['quantity']) {
                    continue;
                }

                $cartItem->update(['quantity' => $itemData['quantity']]);
                $updatedItems[] = $cartItem;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart items updated successfully',
            'data' => $updatedItems
        ]);
    }

    public function removeSelected(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $request->validate([
            'cart_item_ids' => 'required|array',
            'cart_item_ids.*' => 'exists:cart_items,id'
        ]);

        CartItem::whereIn('id', $request->cart_item_ids)
                ->whereHas('cart', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                })
                ->delete();

        // Delete empty carts
        Cart::where('customer_id', $customer->id)
            ->whereDoesntHave('cartItems')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Selected items removed from cart'
        ]);
    }
}
