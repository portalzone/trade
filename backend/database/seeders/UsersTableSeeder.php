<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            
            // 1. Tier 1 Seller
            $seller1 = User::create([
                'email' => 'seller1@test.com',
                'phone_number' => '+2348012345678',
                'password_hash' => Hash::make('password'),
                'full_name' => 'John Seller',
                'username' => 'john_seller',
                'user_type' => 'SELLER',
                'kyc_status' => 'BASIC_VERIFIED',
                'kyc_tier' => 1,
                'account_status' => 'ACTIVE',
                'kyc_submitted_at' => now(),
                'kyc_approved_at' => now(),
            ]);

            Wallet::create([
                'user_id' => $seller1->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            // 2. Tier 2 Business Seller
            $seller2 = User::create([
                'email' => 'business@test.com',
                'phone_number' => '+2348023456789',
                'password_hash' => Hash::make('password'),
                'full_name' => 'TechCorp Nigeria Ltd',
                'username' => 'techcorp_ng',
                'user_type' => 'SELLER',
                'kyc_status' => 'BUSINESS_VERIFIED',
                'kyc_tier' => 2,
                'account_status' => 'ACTIVE',
                'kyc_submitted_at' => now(),
                'kyc_approved_at' => now(),
            ]);

            Wallet::create([
                'user_id' => $seller2->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            // 3. Buyer
            $buyer1 = User::create([
                'email' => 'buyer1@test.com',
                'phone_number' => '+2348034567890',
                'password_hash' => Hash::make('password'),
                'full_name' => 'Jane Buyer',
                'username' => 'jane_buyer',
                'user_type' => 'BUYER',
                'kyc_status' => 'BASIC_VERIFIED',
                'kyc_tier' => 1,
                'account_status' => 'ACTIVE',
                'kyc_submitted_at' => now(),
                'kyc_approved_at' => now(),
            ]);

            Wallet::create([
                'user_id' => $buyer1->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            // 4. Express Rider
            $rider1 = User::create([
                'email' => 'rider1@test.com',
                'phone_number' => '+2348045678901',
                'password_hash' => Hash::make('password'),
                'full_name' => 'Emeka Rider',
                'username' => 'emeka_rider',
                'user_type' => 'RIDER',
                'kyc_status' => 'BASIC_VERIFIED',
                'kyc_tier' => 1,
                'is_rider' => true,
                'account_status' => 'ACTIVE',
                'driver_license_expiry' => now()->addYear(),
                'background_check_expiry' => now()->addYear(),
                'kyc_submitted_at' => now(),
                'kyc_approved_at' => now(),
            ]);

            Wallet::create([
                'user_id' => $rider1->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            // 5. Admin
            $admin = User::create([
                'email' => 'admin@test.com',
                'phone_number' => '+2348056789012',
                'password_hash' => Hash::make('password'),
                'full_name' => 'Admin User',
                'username' => 'admin',
                'user_type' => 'ADMIN',
                'kyc_status' => 'BASIC_VERIFIED',
                'kyc_tier' => 3,
                'account_status' => 'ACTIVE',
                'mfa_enabled' => true,
                'kyc_submitted_at' => now(),
                'kyc_approved_at' => now(),
            ]);

            Wallet::create([
                'user_id' => $admin->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            // 6. Express Food Vendor
            $vendor1 = User::create([
                'email' => 'vendor1@test.com',
                'phone_number' => '+2348067890123',
                'password_hash' => Hash::make('password'),
                'full_name' => 'Mama Put Kitchen',
                'username' => 'mama_put',
                'user_type' => 'SELLER',
                'kyc_status' => 'EXPRESS_VENDOR_VERIFIED',
                'kyc_tier' => 1,
                'is_express_vendor' => true,
                'account_status' => 'ACTIVE',
                'food_safety_cert_expiry' => now()->addYear(),
                'kyc_submitted_at' => now(),
                'kyc_approved_at' => now(),
            ]);

            Wallet::create([
                'user_id' => $vendor1->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            $this->command->info('✅ Created 6 test users with wallets:');
            $this->command->info('   • Tier 1 Seller: seller1@test.com / password');
            $this->command->info('   • Tier 2 Business: business@test.com / password');
            $this->command->info('   • Buyer: buyer1@test.com / password');
            $this->command->info('   • Rider: rider1@test.com / password');
            $this->command->info('   • Admin: admin@test.com / password');
            $this->command->info('   • Express Vendor: vendor1@test.com / password');
        });
    }
}
