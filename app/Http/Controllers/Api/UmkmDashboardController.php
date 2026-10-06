<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Umkm;
use App\Models\ProductView;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UmkmDashboardController extends Controller
{
    public function dashboardData(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        // 1. Statistik dasar
        $totalRevenue = $umkm->orders()->where('status', 'delivered')->sum('grand_total');
        $totalOrders = $umkm->orders()->count();
        $activeProducts = $umkm->products()->where('is_active', true)->count();
        $pendingOrders = $umkm->orders()->where('status', 'pending')->count();

        // Produk dengan stok kurang dari 10
        $lowStockProducts = $umkm->products()->where('stok', '<', 10)->count();

        // Rating rata-rata
        $averageRating = $umkm->products()
            ->join('ratings', 'products.id', '=', 'ratings.product_id')
            ->avg('ratings.rating') ?? 0;

        // 2. Status pesanan
        $orderStatuses = [
            'pending' => $umkm->orders()->where('status', 'pending')->count(),
            'processing' => $umkm->orders()->where('status', 'processing')->count(),
            'shipped' => $umkm->orders()->where('status', 'shipped')->count(),
            'delivered' => $umkm->orders()->where('status', 'delivered')->count(),
            'cancelled' => $umkm->orders()->where('status', 'cancelled')->count(),
        ];

        // 3. Pesanan terbaru (5 pesanan)
        $recentOrders = $umkm->orders()
            ->with(['customer.user', 'shipper'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'kode_order' => $order->kode_order,
                    'customer_name' => $order->customer->nama_customer,
                    'date' => $order->created_at->format('d M Y'),
                    'total' => (float) $order->grand_total,
                    'status' => $order->status
                ];
            });

        // 4. Produk terlaris (5 produk)
        $topProducts = $umkm->products()
            ->with(['category'])
            ->select('products.*',
                DB::raw('(SELECT SUM(quantity) FROM order_items WHERE product_id = products.id) as total_sold'),
                DB::raw('(SELECT AVG(rating) FROM ratings WHERE product_id = products.id AND is_approved = true) as average_rating')
            )
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->nama_produk,
                    'price' => (float) $product->harga,
                    'sold' => (int) $product->total_sold ?? 0,
                    'rating' => (float) $product->average_rating ?? 0
                ];
            });

        // 5. Ulasan terbaru (5 ulasan)
        $recentReviews = Rating::whereHas('product', function ($query) use ($umkm) {
                $query->where('umkm_id', $umkm->id);
            })
            ->with(['customer.user', 'product'])
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->nama_customer,
                    'rating' => $rating->rating,
                    'comment' => $rating->review,
                    'date' => $rating->created_at->diffForHumans(),
                    'product_name' => $rating->product->nama_produk
                ];
            });

        // 6. Statistik hari ini
        $today = Carbon::today();

        $todayOrders = $umkm->orders()
            ->whereDate('created_at', $today)
            ->count();

        $todayRevenue = $umkm->orders()
            ->whereDate('created_at', $today)
            ->where('status', 'delivered')
            ->sum('grand_total');

        $productViews = ProductView::whereHas('product', function ($query) use ($umkm) {
                $query->where('umkm_id', $umkm->id);
            })
            ->whereDate('created_at', $today)
            ->count();

        // 7. Pendapatan bulanan (4 bulan terakhir)
        $fourMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
        $monthlyRevenue = [];

        for ($i = 0; $i < 4; $i++) {
            $month = $fourMonthsAgo->copy()->addMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $revenue = $umkm->orders()
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('grand_total');

            $monthlyRevenue[] = [
                'month' => $month->translatedFormat('M'),
                'revenue' => (float) $revenue
            ];
        }

        // 8. Info UMKM
        $umkmInfo = [
            'nama_umkm' => $umkm->nama_umkm,
            'join_date' => $umkm->created_at->format('M Y'),
            'total_products' => $activeProducts,
            'status' => $umkm->is_approved ? 'Aktif' : 'Menunggu Verifikasi'
        ];

        return response()->json([
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => $totalOrders,
            'active_products' => $activeProducts,
            'average_rating' => round($averageRating, 1),
            'pending_orders' => $pendingOrders,
            'low_stock_products' => $lowStockProducts,
            'order_statuses' => $orderStatuses,
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
            'recent_reviews' => $recentReviews,
            'today_stats' => [
                'today_orders' => $todayOrders,
                'today_revenue' => (float) $todayRevenue,
                'product_views' => $productViews
            ],
            'monthly_revenue' => $monthlyRevenue,
            'umkm_info' => $umkmInfo
        ]);
    }

    public function monthlyRevenue(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $monthlyRevenue = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $sixMonthsAgo->copy()->addMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $revenue = $umkm->orders()
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('grand_total');

            $monthlyRevenue[] = [
                'month' => $month->translatedFormat('M'),
                'revenue' => (float) $revenue,
                'full_month' => $month->translatedFormat('F Y')
            ];
        }

        return response()->json([
            'data' => $monthlyRevenue
        ]);
    }

    public function recentOrders(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $recentOrders = $umkm->orders()
            ->with(['customer.user', 'shipper'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'kode_order' => $order->kode_order,
                    'customer_name' => $order->customer->nama_customer,
                    'customer_email' => $order->customer->user->email,
                    'date' => $order->created_at->format('d M Y'),
                    'total' => (float) $order->grand_total,
                    'status' => $order->status,
                    'nomor_resi' => $order->nomor_resi,
                    'order_items_count' => $order->orderItems()->count()
                ];
            });

        return response()->json([
            'data' => $recentOrders
        ]);
    }

    public function topProducts(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $topProducts = $umkm->products()
            ->with(['category', 'ratings'])
            ->select('products.*',
                DB::raw('(SELECT SUM(quantity) FROM order_items WHERE product_id = products.id) as total_sold'),
                DB::raw('(SELECT COUNT(*) FROM ratings WHERE product_id = products.id AND is_approved = true) as review_count')
            )
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($product) {
                $ratings = $product->ratings->where('is_approved', true);
                $averageRating = $ratings->avg('rating') ?? 0;

                return [
                    'id' => $product->id,
                    'name' => $product->nama_produk,
                    'price' => (float) $product->harga,
                    'sold' => (int) $product->total_sold ?? 0,
                    'rating' => round($averageRating, 1),
                    'stok' => $product->stok,
                    'category' => $product->category->nama_kategori,
                    'foto' => $product->foto ? asset('storage/' . $product->foto) : null
                ];
            });

        return response()->json([
            'data' => $topProducts
        ]);
    }

    public function recentReviews(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $recentReviews = Rating::whereHas('product', function ($query) use ($umkm) {
                $query->where('umkm_id', $umkm->id);
            })
            ->with(['customer.user', 'product'])
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->nama_customer,
                    'customer_email' => $rating->customer->user->email,
                    'rating' => $rating->rating,
                    'comment' => $rating->review,
                    'date' => $rating->created_at->diffForHumans(),
                    'product_name' => $rating->product->nama_produk,
                    'product_id' => $rating->product_id
                ];
            });

        return response()->json([
            'data' => $recentReviews
        ]);
    }

    public function todayStats(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $today = Carbon::today();

        $todayOrders = $umkm->orders()
            ->whereDate('created_at', $today)
            ->count();

        $todayRevenue = $umkm->orders()
            ->whereDate('created_at', $today)
            ->where('status', 'delivered')
            ->sum('grand_total');

        $productViews = ProductView::whereHas('product', function ($query) use ($umkm) {
                $query->where('umkm_id', $umkm->id);
            })
            ->whereDate('created_at', $today)
            ->count();

        $newCustomers = $umkm->orders()
            ->whereDate('created_at', $today)
            ->distinct('customer_id')
            ->count('customer_id');

        return response()->json([
            'data' => [
                'today_orders' => $todayOrders,
                'today_revenue' => (float) $todayRevenue,
                'product_views' => $productViews,
                'new_customers' => $newCustomers,
                'date' => $today->format('d F Y')
            ]
        ]);
    }
}
