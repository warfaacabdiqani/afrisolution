<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = [
            [
                'name' => 'Healthcare / Clinic',
                'slug' => 'clinic',
                'category' => 'healthcare',
                'description' => 'Healthcare and clinical operations.',
                'icon' => 'clinic',
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'name' => 'Dental Clinic',
                'slug' => 'dental',
                'category' => 'healthcare',
                'description' => 'Dental practice workflows.',
                'icon' => 'dental',
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'name' => 'Beauty Salon',
                'slug' => 'beauty-salon',
                'category' => 'beauty',
                'description' => 'Beauty and salon operations.',
                'icon' => 'beauty',
                'status' => 'active',
                'sort_order' => 3,
            ],
            [
                'name' => 'Stadium / Sports Facility',
                'slug' => 'stadium',
                'category' => 'sports',
                'description' => 'Sports venue and event operations.',
                'icon' => 'stadium',
                'status' => 'active',
                'sort_order' => 4,
            ],
        ];

        foreach ($businessTypes as $type) {
            BusinessType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
