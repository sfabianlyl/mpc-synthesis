<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\FinalDocument;
use App\Models\Group;
use App\Models\Response;
use App\Models\SynthesisReport;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ─── 1. Create Core Roles ───────────────────────────────────
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $drafter = User::factory()->create([
            'name' => 'Drafter User',
            'email' => 'drafter@example.com',
            'role' => 'drafter',
        ]);

        // Create 20 Synthesis Team Members
        $synthesisUsers = [];
        for ($i = 1; $i <= 20; $i++) {
            $synthesisUsers[] = User::factory()->create([
                'name' => "Synthesis Member {$i}",
                'email' => "synthesis{$i}@example.com",
                'role' => 'synthesis',
            ]);
        }

        // Create 100 Facilitator Users
        $facilitators = [];
        for ($i = 1; $i <= 100; $i++) {
            $facilitators[] = User::factory()->create([
                'name' => "Facilitator {$i}",
                'email' => "facilitator{$i}@example.com",
                'role' => 'facilitator',
            ]);
        }

        // ─── 2. Create 100 Groups ───────────────────────────────────
        $groups = [];
        for ($i = 1; $i <= 100; $i++) {
            // Each synthesis member gets 5 groups (100 / 20 = 5 groups per synthesis member)
            $synthesisIndex = (int) (($i - 1) / 5);
            $synthesisId = $synthesisUsers[$synthesisIndex]->id;
            $facilitatorId = $facilitators[$i - 1]->id;

            $groups[] = Group::create([
                'name' => "Group " . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                'synthesis_id' => $synthesisId,
                'facilitator_id' => $facilitatorId,
            ]);
        }

        // ─── 3. Create Conversations (Sessions) ─────────────────────
        $conv1 = Conversation::create([
            'title' => 'Session 1: Communion & Digital Outreach',
            'description' => 'Discerning how the parish can build communion and extend its outreach digitally.',
            'status' => 'completed',
        ]);

        $conv2 = Conversation::create([
            'title' => 'Session 2: Co-responsibility in Mission',
            'description' => 'Fostering participation and co-responsibility among lay members and clergy.',
            'status' => 'active',
        ]);

        $conv3 = Conversation::create([
            'title' => 'Session 3: Future Governance & Discernment',
            'description' => 'Reviewing governance structures and paths for collective discernment.',
            'status' => 'active',
        ]);

        // ─── 4. Seed Data for Session 1 (Completed) ──────────────────
        // 100 responses (1 per group)
        $responsesSession1 = [];
        $communionTemplates = [
            'Our group highlighted the vital role of welcoming environments and clear communication in building parish communion. We suggest creating digital welcoming packages for new residents and utilising social media channels to stream services.',
            'We consensus that digital outreach should support, rather than replace, physical gatherings. Parishioners appreciate hybrid prayer meetings, but urge caution about digital isolation.',
            'Digital ministries require training and support. Our group proposes establishing a dedicated IT and media ministry team to assist elderly members in accessing online parish resources.',
            'We discussed the beauty of online liturgy broadcasts, which kept homebound members connected. We believe parish newsletters should be modernised and distributed via email listservs.',
            'A strong communion is built on mutual support. Digital forums can help coordinate parish care networks, connecting volunteers with those in need of assistance.',
        ];

        foreach ($groups as $index => $group) {
            $text = $communionTemplates[$index % count($communionTemplates)] . ' (Group ' . ($index + 1) . ')';
            $responsesSession1[] = Response::create([
                'conversation_id' => $conv1->id,
                'group_id' => $group->id,
                'facilitator_id' => $group->facilitator_id,
                'consensus_text' => $text,
                'submitted_at' => now()->subDays(5),
            ]);
        }

        // 20 Synthesis reports (1 per synthesis member, compiling their 5 assigned groups)
        foreach ($synthesisUsers as $index => $synthesisMember) {
            SynthesisReport::create([
                'conversation_id' => $conv1->id,
                'synthesis_id' => $synthesisMember->id,
                'convergence_text' => "Convergence from groups " . (($index * 5) + 1) . " to " . (($index * 5) + 5) . ":\n- Unanimous support for leveraging digital platforms to keep homebound parishioners connected.\n- Agreement that digital tools should enhance real communion, not replace face-to-face interaction.",
                'consideration_text' => "Matters for Consideration:\n- Concerns regarding digital exclusion of the elderly who may lack internet literacy.\n- Risk of superficial online engagement substituting for deep community involvement.",
                'proposals_text' => "Proposals:\n1. Establish digital training workshops for seniors.\n2. Create a hybrid system of physical and online newsletters.\n3. Form a digital media ministry to manage communications.",
                'llm_generated' => true,
                'status' => 'submitted',
            ]);
        }

        // Seed a booklet for Session 1
        $booklet = FinalDocument::create([
            'title' => 'Synthesis Booklet — Session 1: Communion & Digital Outreach',
            'content_json' => [
                'emerging_themes' => 'Major themes identified include the digital divide, hybrid parish life, and training requirements.',
                'key_convergences' => 'General consensus that online tools are a blessing for outreach but require pastoral coordination.',
                'critical_considerations' => 'Fears of creating a two-tier parish (tech-savvy vs. offline).',
                'priority_proposals' => 'Forming tech guilds and parish email circulars.',
            ],
            'emerging_themes' => 'Major themes identified include the digital divide, hybrid parish life, and training requirements.',
            'created_by' => $drafter->id,
        ]);
        $booklet->conversations()->attach($conv1->id);

        // ─── 5. Seed Data for Session 2 (Active) ─────────────────────
        // 80 responses submitted, 20 pending
        $coresponsibilityTemplates = [
            'Our group discussed lay participation. There is a strong feeling that laity should have a greater voice in decision-making bodies like parish finance committees.',
            'Clergy and laity need to work in close partnership. We propose regular joint reflection days to build trust and shared vision for our parish mission.',
            'Laity are eager to offer their professional skills (management, finance, teaching) to the parish. We need structured channels to onboard volunteer talents.',
            'Co-responsibility means empowering youth. The group suggests establishing a parish youth council with direct representation on the main council.',
            'Tensions sometimes arise between traditional clerical roles and lay leadership. We need clear guidelines defining responsibilities and authority.',
        ];

        for ($i = 0; $i < 80; $i++) {
            $group = $groups[$i];
            $text = $coresponsibilityTemplates[$i % count($coresponsibilityTemplates)] . ' (Group ' . ($i + 1) . ')';
            Response::create([
                'conversation_id' => $conv2->id,
                'group_id' => $group->id,
                'facilitator_id' => $group->facilitator_id,
                'consensus_text' => $text,
                'submitted_at' => now()->subDays(1),
            ]);
        }

        // 20 Synthesis reports (some draft, some submitted, some pending)
        // Let's create reports for the first 16 synthesis members (covering groups 1-80)
        for ($index = 0; $index < 16; $index++) {
            $synthesisMember = $synthesisUsers[$index];
            // Synthesis 0-10 will be submitted, 11-15 will be draft
            $status = $index < 10 ? 'submitted' : 'draft';
            
            SynthesisReport::create([
                'conversation_id' => $conv2->id,
                'synthesis_id' => $synthesisMember->id,
                'convergence_text' => "Convergence on Co-responsibility (groups " . (($index * 5) + 1) . " to " . (($index * 5) + 5) . "):\n- Broad desire for lay involvement in administrative roles.\n- Recognition of lay professionals as an untapped parish asset.",
                'consideration_text' => "Matters for Consideration:\n- Need for clear boundary lines between clerical decision-making and lay advisory roles.\n- Risk of lay burnout if responsibilities are transferred without adequate support.",
                'proposals_text' => "Proposals:\n1. Open finance committee positions to qualified lay professionals.\n2. Standardise volunteer onboarding and training.\n3. Implement annual joint clergy-lay retreats.",
                'llm_generated' => $status === 'submitted',
                'status' => $status,
            ]);
        }
    }
}
