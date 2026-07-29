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
            ['code' => 'DIOS1', 'name' => 'Archdiocese of Manila'],
            ['code' => 'DIOS2', 'name' => 'Diocese of Cubao'],
            ['code' => 'DIOS3', 'name' => 'Diocese of Pasig'],
            ['code' => 'DIOS4', 'name' => 'Archdiocese of Cebu'],
            ['code' => 'DIOS5', 'name' => 'Diocese of Lapu-Lapu'],
            ['code' => 'DIOS6', 'name' => 'Archdiocese of Davao'],
            ['code' => 'DIOS7', 'name' => 'Diocese of Zamboanga'],
            ['code' => 'DIOS8', 'name' => 'Archdiocese of Lingayen-Dagupan'],
            ['code' => 'DIOS9', 'name' => 'Diocese of San Carlos'],
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
