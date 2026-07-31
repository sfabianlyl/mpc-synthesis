<?php

namespace Database\Seeders;

use App\Models\Diocese;
use App\Models\User;
use Illuminate\Database\Seeder;

class PastoralAndDiscernmentMembersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 3 pastoral members per diocese (27 total) and some discernment members.
     */
    public function run(): void
    {
        $dioceses = Diocese::all();
// fabian does slide 27
        // First names for generating user accounts
        $pastoralFirstNames = [
            'KL', // kl
            'Penang', //penang
            'MJD', //mjd
            'Kuching', //kuching
            'Sibu', //sibu
            'Miri', //miri
            'KK', // kk
            'Sandakan', //sandakan
            'Keningau' //keningau
        ];

        $discernmentFirstNames = [
            'Greg' => 0, 
            'Anna' => 6, 
            'Kiko' => 2, 
            'Salomi' => 0, 
            'Retta' => 0,
            'ThomasPaul' => 6,
        ];

        // Create 1 pastoral account per diocese
        foreach ($dioceses as $i=>$diocese) {
            $firstName = $pastoralFirstNames[$i] ?? 'Pastoral';
            $email = strtolower("$firstName@gerejakatolik.my");

            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $firstName,
                    'role' => 'pastoral',
                    'diocese_id' => $diocese->id,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // Create discernment members
        foreach ($discernmentFirstNames as $firstName=>$diocese_index) {
            $email = strtolower("$firstName@gerejakatolik.my");

            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $firstName,
                    'role' => 'discernment',
                    'diocese_id' => $dioceses[$diocese_index]->id,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
            
        }

        $this->command->info('Pastoral and discernment members seeded successfully.');
    }
}
