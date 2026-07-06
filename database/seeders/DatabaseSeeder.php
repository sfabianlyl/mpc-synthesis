<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Group;
use App\Models\Response;
use App\Models\SynthesisReport;
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
                'status' => 'completed',
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
        ];

        // ─── 4. Create 7 Group Facilitators (Users with role 'facilitator') ──
        $facilitators = [];
        for ($i = 1; $i <= 7; $i++) {
            $facilitators[] = User::factory()->create([
                'name' => "Facilitator {$i}",
                'email' => "facilitator{$i}@mpc.demo",
                'role' => 'facilitator',
            ]);
        }

        // ─── 5. Create 2 Synthesis Team Members (Users with role 'synthesis') ──
        $synthesisMembers = [
            User::factory()->create([
                'name' => 'Synthesis Member A',
                'email' => 'synthesis1@mpc.demo',
                'role' => 'synthesis',
            ]),
            User::factory()->create([
                'name' => 'Synthesis Member B',
                'email' => 'synthesis2@mpc.demo',
                'role' => 'synthesis',
            ]),
        ];

        User::factory()->create([
                'name' => 'Drafter 1',
                'email' => 'drafter1@mpc.demo',
                'role' => 'drafter',
            ]);

        // ─── 6. Create 7 Groups (one per facilitator) ───────────────
        $groups = [];
        for ($i = 1; $i <= 7; $i++) {
            $groups[] = Group::create([
                'name' => "Group {$i}",
                'facilitator_id' => $facilitators[$i - 1]->id,
            ]);
        }

        // ─── 6b. Assign conversations to synthesis members via pivot table ──
        // Synthesis Member A gets CIS 1 and CIS 2
        $conversations[0]->synthesisMembers()->attach($synthesisMembers[0]->id); // CIS 1 -> A

        // Synthesis Member B gets CIS 2 and CIS 3
        $conversations[1]->synthesisMembers()->attach($synthesisMembers[1]->id); // CIS 2 -> B

        // ─── 7. Create Responses for CIS 2 (7 group consensus paragraphs) ──
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

        foreach ($groups as $index => $group) {
            Response::create([
                'conversation_id' => $cis2->id,
                'group_id' => $group->id,
                'facilitator_id' => $group->facilitator_id,
                'consensus_text' => $consensusTexts[$index],
                'submitted_at' => now()->subDays(5),
            ]);
        }

        // ─── 8. Create Synthesis Reports for CIS 2 ──────────────────
        // Synthesis Member A: reports for Groups 1-4 (4 responses)
        SynthesisReport::create([
            'conversation_id' => $cis2->id,
            'synthesis_id' => $synthesisMembers[0]->id,
            'convergence_text' => "Convergence from Groups 1-4:\n- Strong concern about lack of pastoral accompaniment for families and irregular situations.\n- Recognition that Catholic families are losing their identity in a secular media landscape.\n- Call for more leadership training and cultural understanding across Sabah, Sarawak, and Semenanjung.\n- Urgent need for young people (ages 25-45) to take up leadership roles alongside the older generation.",
            'consideration_text' => "Matters for Consideration:\n- Need for trained, qualified, and certified personnel for pastoral work to prevent burnout.\n- Funding challenges for employing more lay workers and redefining monetary priorities.\n- Tension between addressing taboo topics and protecting victims from further harm.",
            'proposals_text' => "Proposals:\n1. Establish parish hubs for family support (counselling, after-school programmes, grief groups).\n2. Deploy full-time dedicated persons to accompany families in irregular circumstances.\n3. Redefine roles to enable young and older generations to work together in tandem.\n4. Train, qualify, and certify clergy and laity for specialised pastoral missions.",
            'llm_generated' => true,
            'status' => 'submitted',
        ]);

        // Synthesis Member B: reports for Groups 5-7 (3 responses)
        SynthesisReport::create([
            'conversation_id' => $cis2->id,
            'synthesis_id' => $synthesisMembers[1]->id,
            'convergence_text' => "Convergence from Groups 5-7:\n- Divided but earnest discussion on listening to the voices of the 'excluded', including taboo topics.\n- Unanimous agreement on the need for more employed lay workers, with funding as a key concern.\n- Call for clear job descriptions for parish priests and formal review mechanisms for accountability and synodality.",
            'consideration_text' => "Matters for Consideration:\n- Risk of questioning the integrity of victims and survivors if taboo topics are opened.\n- Funding constraints and whether current priorities reflect true mission commitment.\n- Balancing transparency/accountability with pastoral sensitivity for clergy.",
            'proposals_text' => "Proposals:\n1. Create safe havens and outlets for victims and survivors, with proper training for church responders.\n2. Conduct a deep dive into cost and funding allocation for clergy vs. lay workforce.\n3. Publish detailed job descriptions for parish priests and establish formal review processes.",
            'llm_generated' => true,
            'status' => 'submitted',
        ]);

        // ─── 9. Seed minimal data for CIS 1 and CIS 3 (placeholder) ─
        $cis1 = $conversations[0];
        $cis3 = $conversations[2];

        // CIS 1: one sample response from Group 1
        Response::create([
            'conversation_id' => $cis1->id,
            'group_id' => $groups[0]->id,
            'facilitator_id' => $groups[0]->facilitator_id,
            'consensus_text' => '[Placeholder] Our group discerned the importance of building communion through digital outreach and welcoming environments for all parishioners.',
            'submitted_at' => now()->subDays(25),
        ]);

        // CIS 3: one sample response from Group 1
        Response::create([
            'conversation_id' => $cis3->id,
            'group_id' => $groups[0]->id,
            'facilitator_id' => $groups[0]->facilitator_id,
            'consensus_text' => '[Placeholder] Our group reflected on the future governance of the church and the need for inclusive discernment processes.',
            'submitted_at' => now()->subDays(20),
        ]);

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Event: Malaysian Pastoral Convention (MPC)');
        $this->command->info('Conversations: CIS 1, CIS 2, CIS 3');
        $this->command->info('Groups: 7 (Group 1-7)');
        $this->command->info('Synthesis Member A: CIS 1, CIS 2 | Synthesis Member B: CIS 2, CIS 3');
        $this->command->info('CIS 2 has 7 group responses with full consensus texts.');
    }
}
