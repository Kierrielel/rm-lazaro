<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RmLazaroSeeder extends Seeder
{
    /**
     * php artisan db:seed --class=RmLazaroSeeder
     */
    public function run(): void
    {
        $electrical = Category::create(['name' => 'Electrical', 'description' => 'Wires, outlets, switches, and lighting fixtures']);
        $plumbing   = Category::create(['name' => 'Plumbing', 'description' => 'Pipes, fittings, valves, and plumbing repair supplies']);
        $tools      = Category::create(['name' => 'Tools', 'description' => 'Tools and equipment for home improvement and repair']);

        User::create([
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'phone_no' => '09123456789',
            'name' => 'admin',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'province' => 'No Address',
            'city' => 'No Address',
            'unit' => '',
            'barangay' => '',
        ]);

        User::create([
            'username' => 'user',
            'email' => 'user@gmail.com',
            'phone_no' => '09123459999',
            'name' => 'Juan Makasalanan',
            'password' => Hash::make('user123'),
            'role' => 'customer',
            'province' => 'Bulacan',
            'city' => 'San Ildefonso',
            'unit' => 'N/a',
            'barangay' => 'Calawitan',
        ]);

        $products = [
            [$plumbing->id, 'Wassernison Bidet Complete Set White Nbcs 80W', 'Wassernison', 'Bidet Complete Set', 359.00, 199, 'products/1778124183_Bidet 80Wjpg.jpg'],
            [$plumbing->id, 'Wassernison Hybrid Dual Shower Faucet Whb-602', 'Wassernison', 'High quality hybrid basin faucet. Made of brass and zinc. Easy installation.', 566.50, 200, 'products/1778124378_Dual Shower Faucet.jpg'],
            [$plumbing->id, 'Wassernison Universal Flexible P Trap for 114 and NP12', 'Wassernison', 'Universal Flexible P-Trap for 1 1/4 and 1 1/2” Drain', 274.50, 200, 'products/1778124613_imgi_6_NUF0217_256x.png'],
            [$plumbing->id, 'Wassernison Hybrid Basin Faucet WHB 6258', 'Wassernison', 'Hybrid Basin Faucet', 714.50, 197, 'products/1778124713_imgi_32_NUF1859_2_1024x.png'],
            [$plumbing->id, 'Wassernison Goose Neck Sink Tap Wss604', 'Wassernison', 'Stainless steel, elegant style, easy installation.', 1319.50, 200, 'products/1778124809_imgi_36_NUF1988_1024x.jpg'],
            [$electrical->id, 'Omni 2pcs Universal Outlet with Wide Series Plate WP2-WU', 'Omni', 'Flame-retardant, heat-resistant, 93% copper insides.', 164.50, 200, 'products/1778125064_imgi_14_YAE0795_1_1200x.jpg'],
            [$electrical->id, 'Omni Eco Extension Cord Set 3 Gang 4 Meter Wire - WEE-003', 'Omni', 'Maximum Wattage 2000W, 15A, 250V, BPS Certified Q-1558.', 229.50, 200, 'products/1778125169_imgi_14_YAE0114_1_1200x.jpg'],
            [$electrical->id, 'Omni 3-Gang Wall Universal Adapter with Individual Switch & Power Indicator - WUA-032-PK', 'Omni', 'Individual switch and LED power indicator, 10A 250V.', 249.50, 200, 'products/1778125293_imgi_38_fcf9582a3f50a98cb5e80f3d5179b283_1024x.jpg'],
            [$electrical->id, 'Omni Parallel Ground Rubber Plug Transparent WPR-103', 'Omni', 'Rated Current 15A, Rated Voltage 250V.', 64.50, 200, 'products/1778125765_imgi_6_YAE0421_1_1600x.jpg'],
            [$electrical->id, 'Omni Travel Extension Cord 3 Gang With 3X Usb Outlet USB-303', 'Omni', 'USB port supports 2A fast charging, surge protection, BPS Certified Q-1558.', 769.50, 200, 'products/1778125906_imgi_35_YAE0783-1_1024x.png'],
        ];

        foreach ($products as [$categoryId, $name, $brand, $description, $price, $stock, $image]) {
            Product::create([
                'category_id' => $categoryId,
                'name' => $name,
                'brand' => $brand,
                'description' => $description,
                'price' => $price,
                'stock_quantity' => $stock,
                'image_path' => $image,
            ]);
        }

        $tools; // reserved for future tools-category products
    }
}
