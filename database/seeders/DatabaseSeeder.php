<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationGroup;
use App\Models\Event;
use App\Models\Group;
use App\Models\MasterSynthesis;
use App\Models\Response;
use App\Models\SynthesisCustomQuery;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo data for Malaysian Pastoral Convention.
     */
    public function run(): void
    {
        // ─── 1. Admin User ──────────────────────────────────────────
        $this->call(AdminUserSeeder::class);

        // ─── 1b. Dioceses & Pastoral/Discernment Members ────────────
        $this->call(DiocesesSeeder::class);
        $this->call(PastoralAndDiscernmentMembersSeeder::class);

        // ─── 2. Create Event: Malaysian Pastoral Convention (MPC) ───
        $event = Event::create([
            'title' => 'Malaysian Pastoral Convention (MPC)',
            'description' => 'A national pastoral convention for discernment and synodality.',
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(28),
            'location' => 'Kuala Lumpur, Malaysia',
            'status' => 'active',
        ]);

        // ─── 3. Create 3 Conversations (CIS 1, CIS 2, CIS 3) ───────
        $conversations = [
            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS 1',
                'description' => 'As a catholic faithful, identify ONE area where the Catholic Church of Malaysia is able to contribute to the wellbeing of the country? How may we, the Catholic Church of Malaysia achieve this? What do you think the Holy Spirit is saying to the Church of Malaysia? ',
                'status' => 'active',
            ]),
            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS 2',
                'description' => 'As I listen to the realities of families and the Church today, with all its opportunities and challenges, what is the Holy Spirit inviting me to let go, to continue, or to begin so that I can become an instrument of God’s love to everyone? ',
                'status' => 'active',
            ]),
            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS 3',
                'description' => 'As I listen to the realities of the Rakyat, how can the outreach of the church make the mission of Jesus more visible and move us from information to implementation that brings sustainable benefits for the future generation?',
                'status' => 'active',
            ]),
        ];

        $groups = []; //94 groups
        for ($i = 1; $i <= 94; $i++) {
            $groups[] = Group::create([
                'name' => "Group {$i}",
            ]);
        }

        //ConversationGroups
        foreach($conversations as $conversation){
            foreach($groups as $group){
                $conversationGroups[]=ConversationGroup::create([
                    "conversation_id"=>$conversation->id,
                    "group_id"=>$group->id
                ]);
            }
        }

        // $this->call(TestConsensusSeeder::class);


        // ─── 4. Create 18 Synthesis Team Members (Users with role 'synthesis') ──
        $synthesisMembers = [
            "team_1"=>[
                User::factory()->create([
                    'name' => 'Friar Gerald, OFM',
                    'email' => 'gerald@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Deacon Leslie Petrus',
                    'email' => 'leslie@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Margaret Cor',
                    'email' => 'margaret@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
            ],
            "team_2"=>[
                User::factory()->create([
                    'name' => 'Ramesh Victor',
                    'email' => 'ramesh@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Elizabeth Selly Buja',
                    'email' => 'elizabeth@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Aaron Lim Pek Qin',
                    'email' => 'aaron@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
            ],
            "team_3"=>[
                User::factory()->create([
                    'name' => 'Feona Miranda',
                    'email' => 'feona@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Felix Kong',
                    'email' => 'felix@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Theresa Ratnam',
                    'email' => 'theresa@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
            ],
            "team_4"=>[
                User::factory()->create([
                    'name' => 'Christina Eng',
                    'email' => 'christina@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Fr. Dominic Tan',
                    'email' => 'dominic@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Fr. David Gasikol',
                    'email' => 'david@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
            ],
            "team_5"=>[
                User::factory()->create([
                    'name' => 'Adrian Won',
                    'email' => 'adrian@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Pilis Malim',
                    'email' => 'pilis@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Fettwinda Sipaan',
                    'email' => 'fettwinda@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
            ],
            "team_6"=>[
                User::factory()->create([
                    'name' => 'Sr. Sandra Yap, FSIC',
                    'email' => 'sandra@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Helda Biun',
                    'email' => 'helda@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
                User::factory()->create([
                    'name' => 'Martina Yong',
                    'email' => 'martina@gerejakatolik.my',
                    'role' => 'synthesis',
                ]),
            ],
        ];
        $cis1ConversationGroups=$conversations[0]->conversationGroups;
        $cis2ConversationGroups=$conversations[1]->conversationGroups;
        $cis3ConversationGroups=$conversations[2]->conversationGroups;

        
        foreach($cis1ConversationGroups->slice(0,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_1"][0]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_1"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_1"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis1ConversationGroups->slice(15,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_2"][0]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_2"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_2"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis1ConversationGroups->slice(30,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_3"][0]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_3"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_3"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis1ConversationGroups->slice(45,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_4"][0]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_4"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_4"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis1ConversationGroups->slice(60,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_5"][0]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_5"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_5"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis1ConversationGroups->slice(75,19) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_6"][0]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_6"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_6"][2]->id=>["is_synthesizer"=>false],
            ]);
        }



        foreach($cis2ConversationGroups->slice(0,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_1"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_1"][1]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_1"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis2ConversationGroups->slice(15,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_2"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_2"][1]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_2"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis2ConversationGroups->slice(30,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_3"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_3"][1]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_3"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis2ConversationGroups->slice(45,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_4"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_4"][1]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_4"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis2ConversationGroups->slice(60,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_5"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_5"][1]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_5"][2]->id=>["is_synthesizer"=>false],
            ]);
        }
        foreach($cis2ConversationGroups->slice(75,19) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_6"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_6"][1]->id=>["is_synthesizer"=>true],
                $synthesisMembers["team_6"][2]->id=>["is_synthesizer"=>false],
            ]);
        }



        foreach($cis3ConversationGroups->slice(0,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_1"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_1"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_1"][2]->id=>["is_synthesizer"=>true],
            ]);
        }
        foreach($cis3ConversationGroups->slice(15,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_2"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_2"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_2"][2]->id=>["is_synthesizer"=>true],
            ]);
        }
        foreach($cis3ConversationGroups->slice(30,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_3"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_3"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_3"][2]->id=>["is_synthesizer"=>true],
            ]);
        }
        foreach($cis3ConversationGroups->slice(45,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_4"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_4"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_4"][2]->id=>["is_synthesizer"=>true],
            ]);
        }
        foreach($cis3ConversationGroups->slice(60,15) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_5"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_5"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_5"][2]->id=>["is_synthesizer"=>true],
            ]);
        }
        foreach($cis3ConversationGroups->slice(75,19) as $conversationGroup){
            $conversationGroup->synthesisMembers()->attach([
                $synthesisMembers["team_6"][0]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_6"][1]->id=>["is_synthesizer"=>false],
                $synthesisMembers["team_6"][2]->id=>["is_synthesizer"=>true],
            ]);
        }
        

        

        

        // ─── 6. Create Responses for CIS 2 (7 group consensus paragraphs) ──
        

        // ─── 7. Create Synthesis Reports for CIS 2 ──────────────────
        // Synthesis Member A: reports for Groups 1-4 (4 responses)
        
        $this->call(UploadedDocumentSourceSeeder::class);

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Event: Malaysian Pastoral Convention (MPC)');
        $this->command->info('Conversations: CIS 1, CIS 2, CIS 3');
        $this->command->info('Groups: 94 (Group 1-94)');
        $this->command->info('Synthesis Members: 18 total (6 per conversation)');
        $this->command->info('CIS 1: Synthesis Members 1-6 | CIS 2: Synthesis Members 7-12 | CIS 3: Synthesis Members 13-18');
        $this->command->info('Admin Users: 10');
    }
}
