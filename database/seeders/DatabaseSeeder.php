<?php

namespace Database\Seeders;

use App\ItemCondition;
use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        collect([
            ['Kamera & Fotografi', 'kamera'],
            ['Elektronik', 'plug'],
            ['Gaming', 'gamepad-2'],
            ['Camping & Outdoor', 'tent'],
            ['Peralatan Acara', 'party-popper'],
            ['Alat Kerja', 'hammer'],
            ['Olahraga', 'dumbbell'],
            ['Kendaraan & Aksesoris', 'car'],
            ['Fashion', 'shirt'],
            ['Lainnya', 'box'],
        ])->each(function (array $category): void {
            [$name, $icon] = $category;

            Category::updateOrCreate(
                ['slug' => str($name)->slug()->toString()],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'description' => 'Kategori '.$name.' untuk fondasi awal Pinjemin.',
                    'is_active' => true,
                ],
            );
        });

        $admin = User::query()->firstOrNew(['email' => 'admin@pinjemin.test']);

        $admin->forceFill([
            'name' => 'Administrator Pinjemin',
            'password' => Hash::make('password'),
            'role' => User::RoleAdmin,
        ])->save();

        User::query()->firstOrCreate(
            ['email' => 'user@pinjemin.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
            ],
        );

        $demoUser = User::query()->where('email', 'user@pinjemin.test')->first();
        $placeholderPath = 'items/demo/pinjemin-placeholder.png';

        if (! Storage::disk('public')->exists($placeholderPath)) {
            Storage::disk('public')->put(
                $placeholderPath,
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAACWCAIAAADrOSKFAAAAA3NCSVQICAjb4U/gAAABT0lEQVR4nO3TQQ3AIADAQMC/5yFjRxMFfXpn5zLQ9wC4c2YHwD0JYgWwAlhCrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrABWACuAFcAKYAWwAlgBrAD2XwIuAa8RFj6mAAAAAElFTkSuQmCC'),
            );
        }

        collect([
            ['Canon EOS R50', 'kamera-fotografi', 125000, 'Medan', ItemCondition::LikeNew],
            ['Proyektor Mini Portable', 'elektronik', 75000, 'Jakarta', ItemCondition::Good],
            ['Tenda Camping 4 Orang', 'camping-outdoor', 55000, 'Bandung', ItemCondition::Good],
        ])->each(function (array $data) use ($demoUser, $placeholderPath): void {
            [$name, $categorySlug, $price, $city, $condition] = $data;
            $category = Category::query()->where('slug', $categorySlug)->first();

            if (! $demoUser || ! $category) {
                return;
            }

            $item = Item::query()->updateOrCreate(
                ['slug' => str($name)->slug()->toString()],
                [
                    'user_id' => $demoUser->id,
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => 'Barang demo untuk pengembangan Pinjemin Tahap 2. Data ini membantu melihat marketplace tanpa bergantung pada gambar eksternal.',
                    'condition' => $condition,
                    'daily_price' => $price,
                    'deposit_amount' => null,
                    'quantity' => 1,
                    'city' => $city,
                    'address' => 'Alamat demo tidak ditampilkan di marketplace.',
                    'status' => ItemStatus::Available,
                    'is_active' => true,
                    'rules' => 'Gunakan barang dengan hati-hati dan kembalikan sesuai kondisi awal.',
                ],
            );

            $item->photos()->updateOrCreate(
                ['path' => $placeholderPath],
                ['is_primary' => true, 'sort_order' => 1],
            );
        });
    }
}
