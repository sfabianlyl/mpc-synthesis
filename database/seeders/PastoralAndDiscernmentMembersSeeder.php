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
            'Greg2' => 0, 
            'Anna2' => 6, 
            'Kiko2' => 2, 
            'Salomi2' => 0, 
            'Retta2' => 0,
            'ThomasPaul2' => 6,
        ];

        // Create 1 pastoral account per diocese
        foreach ($dioceses as $i=>$diocese) {
            $firstName = $pastoralFirstNames[$i] ?? 'Pastoral';
            $email1 = strtolower($firstName."01@gerejakatolik.my");
            $email2 = strtolower($firstName."02@gerejakatolik.my");

            User::firstOrCreate(
                ['email' => $email1],
                [
                    'name' => $firstName."01",
                    'role' => 'pastoral',
                    'diocese_id' => $diocese->id,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            User::firstOrCreate(
                ['email' => $email2],
                [
                    'name' => $firstName."02",
                    'role' => 'pastoral',
                    'diocese_id' => $diocese->id,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        //bishops
        User::firstOrCreate(
                ['email' => "bernard@gerejakatolik.my"],
                [
                    'name' => "Bishop Bernard Paul",
                    'role' => 'pastoral',
                    'diocese_id' => 3,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
        User::firstOrCreate(
                ['email' => "julius@gerejakatolik.my"],
                [
                    'name' => "Bishop Julius Gitom",
                    'role' => 'pastoral',
                    'diocese_id' => 8,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
        User::firstOrCreate(
                ['email' => "richard@gerejakatolik.my",
                [
                    'name' => "Bishop Richard Ng",
                    'role' => 'pastoral',
                    'diocese_id' => 6,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

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
