<?php

namespace Database\Seeders;

use App\Models\Diocese;
use Illuminate\Database\Seeder;

class DiocesesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 9 dioceses (DIOS1 through DIOS9) with appropriate names.
     */
    public function run(): void
    {
        $dioceses = [
            ['code' => 'DIOS1', 'name' => 'Archdiocese of Kuala Lumpur'],
            ['code' => 'DIOS2', 'name' => 'Diocese of Penang'],
            ['code' => 'DIOS3', 'name' => 'Diocese of Melaka-Johore'],
            ['code' => 'DIOS4', 'name' => 'Archdiocese of Kuching'],
            ['code' => 'DIOS5', 'name' => 'Diocese of Sibu'],

            ['code' => 'DIOS7', 'name' => 'Diocese of Miri'],
            ['code' => 'DIOS6', 'name' => 'Archdiocese of Kota Kinabalu'],
            ['code' => 'DIOS8', 'name' => 'Diocese of Sandakan'],
            ['code' => 'DIOS9', 'name' => 'Diocese of Keningau'],
        ];

        foreach ($dioceses as $diocese) {
            Diocese::firstOrCreate(
                ['code' => $diocese['code']],
                $diocese
            );
        }

        $this->command->info('9 dioceses seeded successfully.');
    }
}
