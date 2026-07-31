<?php

namespace Database\Seeders;

use App\Models\Conversation;
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
                'description' => 'First session of discernment.',
                'status' => 'active',
            ]),
            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS 2',
                'description' => 'Second session of discernment.',
                'status' => 'active',
            ]),
            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS 3',
                'description' => 'Third session of discernment.',
                'status' => 'active',
            ]),

            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS Sarawak',
                'description' => 'Practice session in Sibu',
                'status' => 'active',
            ]),

            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS Sabah',
                'description' => 'Practice session in Kota Kinabalu',
                'status' => 'active',
            ]),

            Conversation::create([
                'event_id' => $event->id,
                'title' => 'CIS Semenanjung',
                'description' => 'Practice session in Kajang',
                'status' => 'active',
            ]),
        ];

        // ─── 4. Create 18 Synthesis Team Members (Users with role 'synthesis') ──
        $synthesisMembers = [];
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Gregory Pravin Rajah',
            'email' => 'greg@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Salomi, Rgs',
            'email' => 'salomi@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Thomas Paul',
            'email' => 'thomas@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Anna Teresa',
            'email' => 'anna@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Bernadine',
            'email' => 'bernadine@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Gerald',
            'email' => 'gerald@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Francisco',
            'email' => 'kiko@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Sr Retta',
            'email' => 'retta@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        $synthesisMembers[] = User::factory()->create([
            'name' => 'Nelson Ruki',
            'email' => 'nelson@gerejakatolik.my',
            'role' => 'synthesis',
        ]);
        for ($i = 1; $i <= 9; $i++) {
            $synthesisMembers[] = User::factory()->create([
                'name' => "Synthesis Member {$i}",
                'email' => "synthesis{$i}@gerejakatolik.my",
                'role' => 'synthesis',
            ]);
        }

        // ─── 4b. Create 10 Admin Users ──
        for ($i = 1; $i <= 10; $i++) {
            User::factory()->create([
                'name' => "Admin {$i}",
                'email' => "admin{$i}@gerejakatolik.my",
                'role' => 'admin',
            ]);
        }

        User::factory()->create([
            'name' => 'Drafter 1',
            'email' => 'drafter1@gerejakatolik.my',
            'role' => 'drafter',
        ]);

        // ─── 5. Create 94 Groups (without facilitator) ──────────────
        $groups = [];
        for ($i = 1; $i <= 94; $i++) {
            $groups[] = Group::create([
                'name' => "Group {$i}",
            ]);
        }

        // ─── 5b. Assign conversations to synthesis members via pivot table ──
        // Each conversation gets 6 synthesis members assigned
        // CIS 1: Synthesis Members 1-8
        $cis1Members = array_slice($synthesisMembers, 0, 8);
        $cis1MemberIds = array_column($cis1Members, 'id');
        $conversations[0]->synthesisMembers()->attach($cis1MemberIds);

        // CIS 2: Synthesis Members 9-12
        $cis2Members = array_slice($synthesisMembers, 8, 4);
        $cis2MemberIds = array_column($cis2Members, 'id');
        $conversations[1]->synthesisMembers()->attach($cis2MemberIds);

        // CIS 3: Synthesis Members 13-18
        $cis3Members = array_slice($synthesisMembers, 12, 6);
        $cis3MemberIds = array_column($cis3Members, 'id');
        $conversations[2]->synthesisMembers()->attach($cis3MemberIds);

        // CIS Sarawak, Sabah, Semenanjung, all members
        $synthesisMemberIds=array_column($synthesisMembers, 'id');
        $conversations[3]->synthesisMembers()->attach($synthesisMemberIds);
        $conversations[4]->synthesisMembers()->attach($synthesisMemberIds);
        $conversations[5]->synthesisMembers()->attach($synthesisMemberIds);

        // ─── 6. Create Responses for CIS 2 (7 group consensus paragraphs) ──
        $cis2 = $conversations[1]; // CIS 2

        $consensusTexts = [
            // Group 1 → Consensus 1
            'As a group we have discerned that as an institutional church we are not providing enough pastoral accompaniment for families. Efforts to support families are mainly event based or once off. We lack the courage to speak of irregular family situations. The complexity of the modern family requires collaborative ministries for pastoral accompaniment and support. We recommend action for the possibility of our parishes to become hubs for family support such as counselling, after school programmes to keep children in irregular family situations away from questionable company, grief and survivor groups. More initiative must be made towards regular visits to families who are going through difficult moments.',

            // Group 2 → Consensus 2
            'Our consensus for CIS-2 is that we agreed that Catholic families in general are losing their way in maintaining and defending their identity as a catholic family. The counter identity which has become a regular occupant in mainstream media, social media and the online gaming world poses a threat to the nurturing minds of our young and sometimes even old. The institutional church needs to create a more dialogue-able approach when preaching on related matters. The neighbourhood church (BEC) needs to be supportive to each other. But for a more sustainable effort, the mission requires a full time and dedicated person to accompany families in irregular circumstances. The dioceses and parishes need to redefine their monetary priority to provide for just wages to bring on board people for the mission.',

            // Group 3 → Consensus 3
            'We agreed collectively that more learning is required among our church\'s leadership. There needs to be more understanding of the current realities, more understanding of the culture and traditions in sabah, sarawak and semenanjung. Perhaps some clergy should be working beyond the parish or diocese boundaries such as in NGOs, schools, community centres in order to be relevant and to be first responders. As a church we need to overhaul our way of reaching out. Regardless if we are planning to be the first responders to the specialised help, we must be trained, qualified and certified for the mission. Much of our services in church are executed by caring and dedicated people who fall into burnt out or into blunder simply because they were not trained for the mission. We need to relook at our priorities and spend money for the mission.',

            // Group 4 → Consensus 4
            'We unanimously agree that it is time for more young people to take up leadership in our parishes and dioceses. Young people around the ages of 25 to 45 have better insights and are exposed to newer ways of managing and executing plans. The ever fast moving world we live in does not stop for the older generation to catch up and so the younger generation have an upper hand in responding quickly. That being said, we do not recommend removing the older generation from the equation but to redefine roles, so that both the young generation and the older generation may work together in tandem and not in competition for the greater glory of the mission.',

            // Group 5 → Consensus 5
            'As a group we divided on opposing points in regards to listening to the voices of the "excluded". A strong minority of the group believes that we need to be talking about what is regarded as taboo such as LGBTQIA+, alcoholism, rape, sex, bullying and even improper sexual behaviours. Giving people an outlet to name their demons, and a language to articulate themself is both ways towards justice and healing. Our church communities should be safe havens for victims and survivors not only for their benefit but also for the benefit of others who are being groomed, abused or suffering in any kind of way. The opposing concern in the group is that this may lead to the questioning of ingenuity and integrity of the victims and survivors. This may lead them into another conflict and crisis. We as a group however do agree that we as a church need to send more priests, religious and laity to training and studies on this matter before establishing a mission or commission.',

            // Group 6 → Consensus 6
            'Our group is unanimous in our discernment that if we want a whole sustainable plan for the mission of the church, we need more employed lay workers. The only hold back that this group recognizes is funding. We propose that we must deep dive into the cost and funding that goes into our clergy, existing employed workforce, pastoral and otherwise. Are we holding back to satisfy and accommodate our selfish desires and to stay within our comfort zone?',

            // Group 7 → Consensus 7
            'As a group we have discerned that the job description of the parish priest must be listed out in detail and made available to the public. The role of the parish priest can not and should be vague nor up to each parish priest to decide for himself. In order to uphold the integrity of the priesthood and the transparency and accountability of the priest, priests too must be reviewed formally, of their work by the bishop and the parish community. Methods and processes driven by each parish priest must also be evaluated to identify if synodality is resisted or incorporated in their ministry. Our group does present this consensus as an attack on the clergy but as a safeguarding measure against some bad apples in the bunch.',
        ];

        foreach ($consensusTexts as $index => $consensus) {
            Response::create([
                'conversation_id' => $conversations[0]->id,
                'group_id' => $groups[$index]->id,
                'consensus_text' => $consensus,
                'submitted_at' => now()->subDays(5),
            ]);
        }

        // ─── 7. Create Synthesis Reports for CIS 2 ──────────────────
        // Synthesis Member A: reports for Groups 1-4 (4 responses)
        $report1 = MasterSynthesis::create([
            'conversation_id' => $cis2->id,
            'synthesis_id' => $synthesisMembers[0]->id,
            // User-written content
            'convergence_text' => 'The groups strongly converge on the need for enhanced pastoral accompaniment for families.',
            'consideration_text' => 'Key tensions include funding and training requirements for pastoral missions.',
            'proposals_text' => 'We propose establishing dedicated family support hubs across all parishes.',
            'status' => 'submitted',
        ]);

        // Synthesis Member B: reports for Groups 5-7 (3 responses)
        $report2 = MasterSynthesis::create([
            'conversation_id' => $cis2->id,
            'synthesis_id' => $synthesisMembers[1]->id,
            // User-written content
            'convergence_text' => 'There is strong agreement on the need for trained lay workers and safe spaces for victims.',
            'consideration_text' => 'Funding priorities and accountability mechanisms require further discussion.',
            'proposals_text' => 'Establish formal review processes for parish priests and deep-dive into funding allocation.',
            'status' => 'submitted',
        ]);

        // Sample custom queries for Synthesis Member A's report
        SynthesisCustomQuery::create([
            'synthesis_report_id' => $report1->id,
            'prompt' => 'What are the key funding concerns raised by the groups?',
            'response' => [
                'Groups consistently highlight the lack of dedicated funding for pastoral missions',
                'Current monetary priorities may not reflect true mission commitment',
                'The need for just wages to attract full-time lay workers is a recurring concern',
                'A deep dive into clergy vs lay workforce cost allocation is recommended',
            ],
            'provider' => 'mock',
        ]);

        SynthesisCustomQuery::create([
            'synthesis_report_id' => $report1->id,
            'prompt' => 'How do groups view the role of young people in leadership?',
            'response' => [
                'Young people (ages 25-45) should take up leadership roles alongside older generation',
                'Younger generation has exposure to newer ways of managing and executing plans',
                'Recommendation is to redefine roles rather than replace older generation',
            ],
            'provider' => 'mock',
        ]);

        SynthesisCustomQuery::create([
            'synthesis_report_id' => $report2->id,
            'prompt' => 'What are the main concerns about addressing taboo topics?',
            'response' => [
                'Need to create safe outlets for victims and survivors to articulate their experiences',
                'Concern that opening these topics may lead to questioning the integrity of victims',
                'Recommendation to send more priests, religious and laity to training before establishing a mission',
            ],
            'provider' => 'mock',
        ]);

        // ─── 8. Seed Sample Document Sources (PDF Knowledge Base) ───
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
