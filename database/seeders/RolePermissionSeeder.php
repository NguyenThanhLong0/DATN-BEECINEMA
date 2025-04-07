<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tắt ràng buộc khóa ngoại tạm thời
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    
        // Xóa tất cả các permission và role cũ
        Permission::truncate();
        Role::truncate();
    
        // Xoá cache permission (nếu có)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Tạo lại các quyền với guard 'sanctum'
        $permissions = [
            // Authentication
            'auth_register',
            'auth_login',
            'auth_logout',
            'auth_user',
            'auth_change-password',
            'auth_forgot-password',
            'auth_reset-password',
            'auth_verify-email',
            'auth_google_redirect',
            'auth_google_callback',
            'auth_resend-verification-email',

            // User Profile & Related
            'user_profile',
            'user_membership',
            'user_vouchers',

            // Posts
            'posts_index',
            'posts_store',
            'posts_show',
            'posts_update',
            'posts_destroy',

            // Vouchers
            'vouchers_index',
            'vouchers_store',
            'vouchers_show',
            'vouchers_apply',
            'vouchers_remove',
            'vouchers_update',
            'vouchers_destroy',

            // Tickets
            'tickets_store',
            'tickets_history',

            // Choose Seat
            'choose-seat_update',
            'choose-seat_save-information',
            'choose-seat_show',
            'choose-seat_userHoldSeats',

            // Showtime Seat Hold
            'showtime_updateSeatHoldtime',

            // Payment
            'payment_create',
            'payment_vnpay',
            'payment_zalopay',
            'payment_momo',

            // Admin Routes
            'admin_users_index',
            'admin_users_show',
            'admin_users_update',
            'admin_users_create',
            'admin_users_destroy',
            'admin_users_restore',
            'admin_users_force-delete',

            'admin_branches_store',
            'admin_branches_update',
            'admin_branches_destroy',

            'admin_cinemas_store',
            'admin_cinemas_update',
            'admin_cinemas_destroy',

            'admin_ranks_store',
            'admin_ranks_update',
            'admin_ranks_destroy',

            'admin_movies_store',
            'admin_movies_update',
            'admin_movies_destroy',
            'admin_movies_update-active',
            'admin_movies_update-hot',

            'admin_banners_store',
            'admin_banners_update',
            'admin_banners_destroy',

            'admin_movie-reviews_store',
            'admin_movie-reviews_update',
            'admin_movie-reviews_destroy',

            'admin_contact_store',
            'admin_contact_update',
            'admin_contact_destroy',

            'admin_permission_index',
            'admin_permission_show',
            'admin_permission_store',
            'admin_permission_update',
            'admin_permission_destroy',

            'admin_roles_index',
            'admin_roles_show',
            'admin_roles_store',
            'admin_roles_update',
            'admin_roles_destroy',
            // Admin Cinema Routes
            'admin_cinema_foods_store',
            'admin_cinema_foods_update',
            'admin_cinema_foods_destroy',

            'admin_cinema_seat-templates_store',
            'admin_cinema_seat-templates_update',
            'admin_cinema_seat-templates_destroy',

            'admin_cinema_rooms_store',
            'admin_cinema_rooms_update',
            'admin_cinema_rooms_destroy',

            'admin_cinema_combos_store',
            'admin_cinema_combos_update',
            'admin_cinema_combos_destroy',

            'admin_cinema_type-rooms_store',
            'admin_cinema_type-rooms_update',
            'admin_cinema_type-rooms_destroy',

            'admin_cinema_type-seats_store',
            'admin_cinema_type-seats_update',
            'admin_cinema_type-seats_destroy',

            'admin_cinema_showtimes_store',
            'admin_cinema_showtimes_update',
            'admin_cinema_showtimes_destroy',
            'admin_cinema_showtimes_copy',

            // Public Routes
            'public_branches_index',
            'public_branches_show',
            'public_cinemas_index',
            'public_cinemas_show',
            'public_showtimespage',
            'public_showtimemovie',
            'public_foods_index',
            'public_foods_show',
            'public_ranks_index',
            'public_ranks_show',
            'public_seat-templates_index',
            'public_seat-templates_show',
            'public_rooms_index',
            'public_rooms_show',
            'public_combos_index',
            'public_combos_show',
            'public_combosActive',
            'public_type-rooms_index',
            'public_type-rooms_show',
            'public_type-seats_index',
            'public_type-seats_show',
            'public_movies_index',
            'public_movies_tab',
            'public_movies_show',
            'public_banners_index',
            'public_banners_show',
            'public_tickets_filter',
            'public_movie-reviews_index',
            'public_movie-reviews_show',
            'public_contact_index',
            'public_contact_show',

            // Payment Callbacks
            'payment_zalopay_callback',
            'payment_vnpay_return',
            'payment_momo_ipn',

            // Revenue and Overview
            'revenue_by_combo',
            'revenue_by_food',
            'revenue_by_movie',
            'revenue_statistics',
            'revenue_total',
            'revenue_ticket-statistics',
            'revenue_customer',
            'revenue_booking-trends',
            'overview_seatOccupancyByDay',
            'overview_seatOccupancyByMonth',
            'overview_dashboard',
            'overview_revenueStatistics',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
    
        // Tạo các vai trò với guard 'sanctum'
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $adminCinema = Role::firstOrCreate(['name' => 'admin_cinema']);
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $member = Role::firstOrCreate(['name' => 'member']);
    
        // Gán quyền cho role với guard 'sanctum'
        $admin->givePermissionTo([
            'admin_users_index',
            'admin_users_show',
            'admin_users_update',
            'admin_users_create',
            'admin_users_destroy',
            'admin_users_restore',
            'admin_users_force-delete',

            'admin_branches_store',
            'admin_branches_update',
            'admin_branches_destroy',

            'admin_cinemas_store',
            'admin_cinemas_update',
            'admin_cinemas_destroy',

            'admin_ranks_store',
            'admin_ranks_update',
            'admin_ranks_destroy',

            'admin_movies_store',
            'admin_movies_update',
            'admin_movies_destroy',
            'admin_movies_update-active',
            'admin_movies_update-hot',

            'admin_banners_store',
            'admin_banners_update',
            'admin_banners_destroy',

            'admin_movie-reviews_store',
            'admin_movie-reviews_update',
            'admin_movie-reviews_destroy',

            'admin_contact_store',
            'admin_contact_update',
            'admin_contact_destroy',

            'admin_permission_index',
            'admin_permission_show',
            'admin_permission_store',
            'admin_permission_update',
            'admin_permission_destroy',

            'admin_roles_index',
            'admin_roles_show',
            'admin_roles_store',
            'admin_roles_update',
            'admin_roles_destroy',

            // Admin Cinema Routes
            'admin_cinema_foods_store',
            'admin_cinema_foods_update',
            'admin_cinema_foods_destroy',

            'admin_cinema_seat-templates_store',
            'admin_cinema_seat-templates_update',
            'admin_cinema_seat-templates_destroy',

            'admin_cinema_rooms_store',
            'admin_cinema_rooms_update',
            'admin_cinema_rooms_destroy',

            'admin_cinema_combos_store',
            'admin_cinema_combos_update',
            'admin_cinema_combos_destroy',

            'admin_cinema_type-rooms_store',
            'admin_cinema_type-rooms_update',
            'admin_cinema_type-rooms_destroy',

            'admin_cinema_type-seats_store',
            'admin_cinema_type-seats_update',
            'admin_cinema_type-seats_destroy',

            'admin_cinema_showtimes_store',
            'admin_cinema_showtimes_update',
            'admin_cinema_showtimes_destroy',
            'admin_cinema_showtimes_copy',
        ]);
    
        $adminCinema->givePermissionTo([
            // Admin Cinema Routes
            'admin_cinema_foods_store',
            'admin_cinema_foods_update',
            'admin_cinema_foods_destroy',

            'admin_cinema_seat-templates_store',
            'admin_cinema_seat-templates_update',
            'admin_cinema_seat-templates_destroy',

            'admin_cinema_rooms_store',
            'admin_cinema_rooms_update',
            'admin_cinema_rooms_destroy',

            'admin_cinema_combos_store',
            'admin_cinema_combos_update',
            'admin_cinema_combos_destroy',

            'admin_cinema_type-rooms_store',
            'admin_cinema_type-rooms_update',
            'admin_cinema_type-rooms_destroy',

            'admin_cinema_type-seats_store',
            'admin_cinema_type-seats_update',
            'admin_cinema_type-seats_destroy',

            'admin_cinema_showtimes_store',
            'admin_cinema_showtimes_update',
            'admin_cinema_showtimes_destroy',
            'admin_cinema_showtimes_copy',
        ]);
    
        // $staff->givePermissionTo(['sell_ticket']);
    }
}
