<?php

namespace App\Http\Controllers\Api;

use App\Models\Umkm;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Get complete dashboard statistics
     */
    public function statistics()
    {
        // Cache key berdasarkan jam untuk refresh setiap jam
        $cacheKey = 'dashboard_statistics_' . date('Y_m_d_H');

        return Cache::remember($cacheKey, 3600, function () {
            // 1. BASIC STATISTICS - Query sederhana tanpa join berlebihan
            $totalCustomers = Customer::count();
            $totalUmkms = Umkm::count();
            $totalProducts = Product::count();
            $totalCategories = Category::count();
            $totalOrders = Order::count();
            $totalRatings = Rating::count();

            // 2. RECENT DATA - Ambil hanya data yang diperlukan
            // Recent Customers
            $recentCustomers = Customer::select(['id', 'nama_customer', 'user_id', 'created_at'])
                ->with(['user:id,email'])
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'nama' => $customer->nama_customer,
                        'email' => $customer->user->email ?? 'N/A',
                        'created_at' => $customer->created_at->format('d M Y'),
                    ];
                });

            // Recent UMKM
            $recentUmkms = Umkm::select(['id', 'nama_umkm', 'user_id', 'is_approved', 'created_at'])
                ->with(['user:id,email'])
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($umkm) {
                    return [
                        'id' => $umkm->id,
                        'nama_umkm' => $umkm->nama_umkm,
                        'email' => $umkm->user->email ?? 'N/A',
                        'is_approved' => (bool) $umkm->is_approved,
                        'created_at' => $umkm->created_at->format('d M Y'),
                    ];
                });

            // Recent Orders
            $recentOrders = Order::select(['id', 'kode_order', 'customer_id', 'umkm_id', 'status', 'created_at'])
                ->with([
                    'customer:id,nama_customer',
                    'umkm:id,nama_umkm'
                ])
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'kode_order' => $order->kode_order,
                        'customer' => $order->customer->nama_customer ?? 'Tidak diketahui',
                        'umkm' => $order->umkm->nama_umkm ?? 'Tidak diketahui',
                        'status' => $order->status,
                        'created_at' => $order->created_at->format('d M Y H:i'),
                    ];
                });

            // 3. ORDER STATUS DISTRIBUTION
            $orderStatuses = Order::select('status')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Pastikan semua status ada, meskipun 0
            $allStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            $statusDistribution = [];
            foreach ($allStatuses as $status) {
                $statusDistribution[$status] = $orderStatuses[$status] ?? 0;
            }

            // 4. UMKM STATUS DISTRIBUTION
            $umkmStatuses = [
                'approved' => Umkm::where('is_approved', true)->count(),
                'pending' => Umkm::where('is_approved', false)->count(),
            ];

            return response()->json([
                'basic_stats' => [
                    'total_customers' => (int) $totalCustomers,
                    'total_umkms' => (int) $totalUmkms,
                    'total_products' => (int) $totalProducts,
                    'total_categories' => (int) $totalCategories,
                    'total_orders' => (int) $totalOrders,
                    'total_ratings' => (int) $totalRatings,
                ],
                'order_statuses' => $statusDistribution,
                'umkm_statuses' => $umkmStatuses,
                'recent_data' => [
                    'customers' => $recentCustomers,
                    'umkms' => $recentUmkms,
                    'orders' => $recentOrders,
                ],
            ]);
        });
    }

    /**
     * Get charts data for dashboard
     * Data untuk 4 bulan terakhir dengan format yang konsisten
     */
    public function chartsData()
    {
        $cacheKey = 'dashboard_charts_' . date('Y_m_d_H');

        return Cache::remember($cacheKey, 3600, function () {
            // 1. ORDER CHART - 4 bulan terakhir
            $orderData = [];
            $currentDate = now();

            for ($i = 3; $i >= 0; $i--) {
                $targetDate = $currentDate->copy()->subMonths($i);

                // Format bulan dalam bahasa Indonesia
                $monthNames = [
                    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
                    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
                ];

                $monthNumber = $targetDate->month;
                $monthName = $monthNames[$monthNumber] ?? $targetDate->format('M');

                // Hitung jumlah order bulan ini
                $ordersCount = Order::whereYear('created_at', $targetDate->year)
                    ->whereMonth('created_at', $targetDate->month)
                    ->count();

                $orderData[] = [
                    'month' => $monthName,
                    'orders' => (int) $ordersCount,
                    'year' => $targetDate->year,
                    'month_number' => $monthNumber,
                    'full_date' => $targetDate->format('F Y'),
                ];
            }

            // 2. TOP 3 UMKM DENGAN ORDER TERBANYAK
            $topUmkms = Umkm::select(['id', 'nama_umkm'])
                ->withCount(['orders as order_count' => function($query) {
                    $query->where('status', '!=', 'cancelled');
                }])
                ->having('order_count', '>', 0)
                ->orderBy('order_count', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($umkm) {
                    return [
                        'id' => $umkm->id,
                        'nama_umkm' => $umkm->nama_umkm,
                        'order_count' => (int) $umkm->order_count,
                    ];
                });

            // 3. TOP 3 PRODUCTS DENGAN RATING TERTINGGI
            $topProducts = Product::select(['id', 'nama_produk', 'umkm_id'])
                ->with(['umkm:id,nama_umkm'])
                ->withCount(['ratings as total_ratings'])
                ->withAvg('ratings as average_rating', 'rating')
                ->having('total_ratings', '>', 0)
                ->orderBy('average_rating', 'desc')
                ->orderBy('total_ratings', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($product) {
                    $averageRating = $product->average_rating ?? 0;

                    return [
                        'id' => $product->id,
                        'nama_produk' => $product->nama_produk,
                        'average_rating' => round($averageRating, 1),
                        'total_ratings' => (int) $product->total_ratings,
                        'umkm' => $product->umkm->nama_umkm ?? 'Tidak diketahui',
                    ];
                });

            return response()->json([
                'order_chart' => $orderData,
                'top_umkms' => $topUmkms,
                'top_products' => $topProducts,
            ]);
        });
    }

    /**
     * Get quick stats for immediate display
     * Data sederhana untuk loading cepat
     */
    public function quickStats()
    {
        // Tidak menggunakan cache untuk quick stats agar selalu real-time
        $totalCustomers = Customer::count();
        $totalUmkms = Umkm::count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $pendingUmkms = Umkm::where('is_approved', false)->count();

        return response()->json([
            'total_customers' => (int) $totalCustomers,
            'total_umkms' => (int) $totalUmkms,
            'total_products' => (int) $totalProducts,
            'total_orders' => (int) $totalOrders,
            'pending_orders' => (int) $pendingOrders,
            'pending_umkms' => (int) $pendingUmkms,
        ]);
    }

    /**
     * Get user-specific statistics
     */
    public function userStatistics(Request $request)
    {
        $user = $request->user();
        $data = [
            'orders_count' => 0,
            'ratings_count' => 0,
            'products_count' => 0,
        ];

        try {
            switch ($user->role) {
                case 'customer':
                    $customer = Customer::where('user_id', $user->id)->first();
                    if ($customer) {
                        $data['orders_count'] = Order::where('customer_id', $customer->id)->count();
                        $data['ratings_count'] = Rating::where('customer_id', $customer->id)->count();
                    }
                    break;

                case 'umkm':
                    $umkm = Umkm::where('user_id', $user->id)->first();
                    if ($umkm) {
                        $data['orders_count'] = Order::where('umkm_id', $umkm->id)->count();
                        $data['products_count'] = Product::where('umkm_id', $umkm->id)->count();
                        $data['ratings_count'] = Rating::whereHas('order', function ($query) use ($umkm) {
                            $query->where('umkm_id', $umkm->id);
                        })->count();
                    }
                    break;

                case 'admin':
                    // Admin melihat statistik global
                    $data['orders_count'] = Order::count();
                    $data['ratings_count'] = Rating::count();
                    $data['products_count'] = Product::count();
                    break;
            }
        } catch (\Exception $e) {
            // Log error jika diperlukan
            // \Log::error('Error in userStatistics: ' . $e->getMessage());
        }

        return response()->json($data);
    }

    /**
     * Clear dashboard cache (untuk development/testing)
     */
    public function clearCache()
    {
        Cache::forget('dashboard_statistics_' . date('Y_m_d_H'));
        Cache::forget('dashboard_charts_' . date('Y_m_d_H'));

        return response()->json([
            'success' => true,
            'message' => 'Dashboard cache cleared successfully'
        ]);
    }

    /**
     * Get monthly order summary for current year
     * Endpoint tambahan untuk data yang lebih detail
     */
    public function monthlyOrderSummary()
    {
        $cacheKey = 'monthly_order_summary_' . date('Y');

        return Cache::remember($cacheKey, 3600, function () {
            $currentYear = date('Y');
            $monthlyData = [];

            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];

            for ($month = 1; $month <= 12; $month++) {
                $ordersCount = Order::whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $month)
                    ->count();

                $monthlyData[] = [
                    'month' => $monthNames[$month] ?? "Bulan $month",
                    'orders' => (int) $ordersCount,
                    'month_number' => $month,
                    'year' => $currentYear,
                ];
            }

            return response()->json([
                'year' => $currentYear,
                'monthly_data' => $monthlyData,
                'total_orders' => array_sum(array_column($monthlyData, 'orders')),
            ]);
        });
    }
}
