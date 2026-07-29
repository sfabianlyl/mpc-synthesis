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

        // First names for generating user accounts
        $pastoralFirstNames = [
            'Antonio', 'Maria', 'Jose', 'Rosa', 'Carlos',
            'Ana', 'Pedro', 'Luz', 'Juan', 'Carmen',
            'Miguel', 'Teresa', 'Francisco', 'Isabel', 'Diego',
        ];

        $discernmentFirstNames = [
            'Rafael', 'Gabriela', 'Emilio', 'Lucia', 'Andres',
            'Patricia', 'Roberto', 'Sofia', 'Fernando', 'Valentina',
        ];

        // Create 3 pastoral members per diocese
        foreach ($dioceses as $diocese) {
            for ($i = 1; $i <= 3; $i++) {
                $firstName = $pastoralFirstNames[($diocese->id - 1) * 3 + $i - 1] ?? 'Pastoral';
                $email = strtolower($firstName.'.pastoral'.$i.'@'.strtolower($diocese->code).'.example.com');

                User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $firstName.' Pastoral '.$i,
                        'role' => 'pastoral',
                        'diocese_id' => $diocese->id,
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                    ]
                );
            }
        }

        // Create 2 discernment members per diocese
        foreach ($dioceses as $diocese) {
            for ($i = 1; $i <= 2; $i++) {
                $firstName = $discernmentFirstNames[($diocese->id - 1) * 2 + $i - 1] ?? 'Discernment';
                $email = strtolower($firstName.'.discernment'.$i.'@'.strtolower($diocese->code).'.example.com');

                User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $firstName.' Discernment '.$i,
                        'role' => 'discernment',
                        'diocese_id' => $diocese->id,
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('Pastoral and discernment members seeded successfully.');
    }
}
