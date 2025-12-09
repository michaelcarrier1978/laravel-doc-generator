<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
        ]);
        
        return response()->json($user, 201);
    }
    
    public function index()
    {
        $users = User::with('posts')
            ->where('active', true)
            ->get();
            
        return response()->json($users);
    }
    
    /**
     * Get top revenue-generating users with recent activity
     */
    public function topRevenueUsers()
    {
        $users = User::select('users.*')
            ->selectRaw('SUM(orders.total) as total_revenue')
            ->selectRaw('COUNT(DISTINCT orders.id) as order_count')
            ->selectRaw('MAX(orders.created_at) as last_order_date')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', now()->subMonths(6))
            ->whereHas('subscription', function($query) {
                $query->where('tier', '!=', 'free')
                      ->where('status', 'active');
            })
            ->groupBy('users.id')
            ->having('total_revenue', '>', 10000)
            ->orderByDesc('total_revenue')
            ->limit(100)
            ->get();
            
        return response()->json($users);
    }
    
    /**
     * Complex user search with multiple relationship filters
     */
    public function advancedSearch($searchTerm)
    {
        $users = User::with(['posts' => function($query) {
                $query->where('published', true)
                      ->orderBy('created_at', 'desc')
                      ->limit(5);
            }, 'subscription', 'orders.items'])
            ->where(function($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                      ->orWhereHas('posts', function($q) use ($searchTerm) {
                          $q->where('title', 'LIKE', "%{$searchTerm}%");
                      });
            })
            ->whereDoesntHave('bans', function($query) {
                $query->where('expires_at', '>', now())
                      ->orWhereNull('expires_at');
            })
            ->when(request('role'), function($query, $role) {
                $query->whereHas('roles', function($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->withCount(['posts', 'orders'])
            ->get();
            
        return response()->json($users);
    }
    
    /**
     * Get users who haven't engaged recently but were previously active
     */
    public function churningUsers()
    {
        $users = User::whereHas('orders', function($query) {
                $query->where('created_at', '>=', now()->subYear());
            })
            ->whereDoesntHave('orders', function($query) {
                $query->where('created_at', '>=', now()->subDays(90));
            })
            ->whereDoesntHave('logins', function($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            })
            ->with(['lastOrder' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->withCount([
                'orders as lifetime_orders',
                'orders as lifetime_revenue' => function($query) {
                    $query->select(DB::raw('SUM(total)'));
                }
            ])
            ->having('lifetime_revenue', '>', 500)
            ->orderBy('lifetime_revenue', 'desc')
            ->get();
            
        return response()->json($users);
    }
    
    /**
     * Oracle database specific query with subqueries
     */
    public function userActivityReport()
    {
        $users = DB::table('users')
            ->select('users.*')
            ->selectSub(function($query) {
                $query->selectRaw('COUNT(*)')
                      ->from('orders')
                      ->whereColumn('orders.user_id', 'users.id')
                      ->where('orders.created_at', '>=', DB::raw("ADD_MONTHS(SYSDATE, -3)"));
            }, 'recent_orders')
            ->selectSub(function($query) {
                $query->selectRaw('SUM(total)')
                      ->from('orders')
                      ->whereColumn('orders.user_id', 'users.id')
                      ->where('orders.status', 'completed');
            }, 'total_spent')
            ->selectSub(function($query) {
                $query->selectRaw('MAX(created_at)')
                      ->from('user_logins')
                      ->whereColumn('user_logins.user_id', 'users.id');
            }, 'last_login')
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('subscriptions')
                      ->whereColumn('subscriptions.user_id', 'users.id')
                      ->where('subscriptions.status', 'active')
                      ->where('subscriptions.tier', 'premium');
            })
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('support_tickets')
                      ->whereColumn('support_tickets.user_id', 'users.id')
                      ->where('support_tickets.status', 'open')
                      ->where('support_tickets.priority', 'high');
            })
            ->orderBy('total_spent', 'desc')
            ->get();
            
        return response()->json($users);
    }
    
    /**
     * Multi-table join with complex conditions
     */
    public function eligibleForUpgrade()
    {
        $users = User::select('users.*', 'subscriptions.tier', 'subscriptions.expires_at')
            ->join('subscriptions', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.created_at', '>=', now()->subMonths(3));
            })
            ->leftJoin('feature_usage', 'users.id', '=', 'feature_usage.user_id')
            ->where('subscriptions.tier', 'basic')
            ->where('subscriptions.status', 'active')
            ->whereRaw('DATEDIFF(subscriptions.expires_at, NOW()) > 30')
            ->havingRaw('COUNT(DISTINCT orders.id) >= 5')
            ->havingRaw('AVG(feature_usage.usage_count) > 100')
            ->orWhereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('referrals')
                      ->whereColumn('referrals.referrer_id', 'users.id')
                      ->where('referrals.status', 'completed')
                      ->havingRaw('COUNT(*) >= 3');
            })
            ->groupBy('users.id', 'subscriptions.tier', 'subscriptions.expires_at')
            ->orderByRaw('COUNT(orders.id) * AVG(orders.total) DESC')
            ->limit(50)
            ->get();
            
        return response()->json($users);
    }
    
    /**
     * Recursive CTE query (for databases that support it)
     */
    public function userReferralTree($userId)
    {
        $tree = DB::select("
            WITH RECURSIVE referral_tree AS (
                SELECT 
                    id,
                    name,
                    email,
                    referred_by,
                    0 as level,
                    CAST(id AS CHAR(200)) as path
                FROM users
                WHERE id = ?
                
                UNION ALL
                
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.referred_by,
                    rt.level + 1,
                    CONCAT(rt.path, '->', u.id)
                FROM users u
                INNER JOIN referral_tree rt ON u.referred_by = rt.id
                WHERE rt.level < 5
            )
            SELECT 
                rt.*,
                COUNT(o.id) as total_orders,
                SUM(o.total) as total_revenue
            FROM referral_tree rt
            LEFT JOIN orders o ON rt.id = o.user_id AND o.status = 'completed'
            GROUP BY rt.id, rt.name, rt.email, rt.referred_by, rt.level, rt.path
            ORDER BY rt.level, rt.id
        ", [$userId]);
        
        return response()->json($tree);
    }
    
    /**
     * Performance-critical paginated query with window functions
     */
    public function rankedUsers()
    {
        $users = DB::table('users')
            ->select('users.*')
            ->selectRaw('
                ROW_NUMBER() OVER (PARTITION BY users.country ORDER BY total_spent DESC) as country_rank,
                DENSE_RANK() OVER (ORDER BY total_spent DESC) as global_rank,
                PERCENT_RANK() OVER (ORDER BY total_spent) as percentile
            ')
            ->selectSub(function($query) {
                $query->selectRaw('SUM(total)')
                      ->from('orders')
                      ->whereColumn('orders.user_id', 'users.id')
                      ->where('status', 'completed');
            }, 'total_spent')
            ->whereNotNull('country')
            ->havingRaw('total_spent > (SELECT AVG(total) FROM orders WHERE status = "completed")')
            ->orderBy('global_rank')
            ->paginate(50);
            
        return response()->json($users);
    }
}