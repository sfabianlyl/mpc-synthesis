<?php

namespace Database\Seeders;

use App\Models\DocumentSourceChunk;
use App\Models\UploadedDocumentSource;
use Illuminate\Database\Seeder;

class UploadedDocumentSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sampleDocuments = [
            [
                'title' => 'Evangelii Gaudium',
                'original_filename' => 'evangelii_gaudium.pdf',
                'file_path' => 'document-sources/pdfs/evangelii_gaudium.pdf',
                'document_type' => 'encyclical',
                'author' => 'Pope Francis',
                'publication_date' => '2013-10-24',
                'cleaned_markdown' => "# Evangelii Gaudium\n\nThe Joy of the Gospel\n\nOn Evangelization in Today's World\n\nINTRODUCTION\n\nThe call to evangelize is at the very heart of the Christian identity itself. This spiritual journey, therefore, is not a path of despair but one filled with hope and joy.\n\n## A NEW PASTORAL STAGE\n\nThe Church must embark on a new pastoral stage of evangelization in which the conviction of the faith is the driving force. Every Christian is challenged to be a missionary disciple.\n\n### THE CHALLENGE OF EVANGELIZATION\n\nEvangelization is about proclaiming Jesus Christ in every circumstance by word and witness. The joy of the Gospel fills the hearts and lives of all who encounter Him.\n\n## CULTURE AND THE CHALLENGE OF EVANGELIZATION\n\nIn today's world, we face numerous challenges including secularism, individualism, and the rapid pace of technological change. Yet these challenges also present opportunities for new forms of witness.\n\n### GLOBALIZATION AND CULTURAL EXCHANGE\n\nGlobalization has created both unity and division. While it enables greater communication and understanding, it can also lead to cultural homogenization that erodes local identities.\n\n## CLERGY AND PASTORAL MINISTERS\n\nPriests and deacons have the specific task of making Christ present in a sacramental way, especially through the preaching of the Word and the governance of the community.",
                'processing_status' => 'completed',
                'chunk_count' => 0,
            ],
            [
                'title' => 'Laudato Si',
                'original_filename' => 'laudato_si.pdf',
                'file_path' => 'document-sources/pdfs/laudato_si.pdf',
                'document_type' => 'encyclical',
                'author' => 'Pope Francis',
                'publication_date' => '2015-05-24',
                'cleaned_markdown' => "# Laudato Si\n\nOn Care for Our Common Home\n\nOn Integral Ecology\n\nINTRODUCTION\n\nOur common home is like a sister with whom we share our life and almost a sister who raises her voice as we devastate her.\n\n## WHAT IS HAPPENING TO THE ENVIRONMENT\n\nThe environment and the quality of human life are intimately connected. The degradation of the environment is linked to a broader cultural decline.\n\n### GLOBAL WARMING AND CLIMATE CHANGE\n\nScientific consensus clearly indicates that global warming is real and primarily caused by human activities. Greenhouse gas emissions from burning fossil fuels are the main contributor.\n\n### LOSS OF BIODIVERSITY\n\nSpecies are disappearing at an alarming rate due to habitat destruction, pollution, climate change, and overexploitation. This loss affects ecosystem services that humans depend upon.\n\n## THE TEACHING OF THE CHURCH\n\nThe Church's social teaching has increasingly emphasized care for creation. From Pope John XXIII to Pope Francis, there has been a growing recognition of our responsibility toward the environment.",
                'processing_status' => 'completed',
                'chunk_count' => 0,
            ],
            [
                'title' => 'Amoris Laetitia',
                'original_filename' => 'amoris_laetitia.pdf',
                'file_path' => 'document-sources/pdfs/amoris_laetitia.pdf',
                'document_type' => 'apostolic_exhortation',
                'author' => 'Pope Francis',
                'publication_date' => '2016-03-19',
                'cleaned_markdown' => "# Amoris Laetitia\n\nThe Joy of Love\n\nOn Love in the Family\n\nINTRODUCTION\n\nFollowing the Two Synods of Bishops on the Family, I feel motivated to offer this apostolic exhortation that seeks once again to encourage and guide the Church toward a deeper understanding of family life.\n\n## LOVE IN THE FAMILY\n\nThe family is founded and built up by married love. The husband and wife, through their mutual gift of themselves, form one body and become one flesh.\n\n### THE DOMESTIC CHURCH\n\nThe family is the first community where the faith is transmitted from generation to generation. It is called the Domestic Church because it is a cell of the larger Church.\n\n## PASTORAL DISCERNMENT\n\nPastors must know that, for the good of all, it is necessary also to set boundaries. But at the same time, it is essential to accompany individuals with patience and understanding.\n\n### CARE FOR THE VULNERABLE\n\nSpecial attention must be given to children, the elderly, and those suffering from family difficulties. Every person deserves dignity and respect.",
                'processing_status' => 'completed',
                'chunk_count' => 0,
            ],
        ];

        foreach ($sampleDocuments as $doc) {
            $source = UploadedDocumentSource::create([
                ...$doc,
                'uploaded_by' => 1, // Admin user
            ]);

            // Create sample chunks for each document
            $sections = preg_split('/^## /m', $doc['cleaned_markdown'], -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach (array_filter($sections) as $index => $section) {
                DocumentSourceChunk::create([
                    'source_id' => $source->id,
                    'chunk_index' => $index,
                    'chunk_content' => $section,
                    'title' => $doc['title'],
                    'section_heading' => preg_match('/^##\s+(.+)$/', $section, $m) ? $m[1] : null,
                    'word_count' => str_word_count($section),
                ]);
            }

            // Update chunk count
            $source->update(['chunk_count' => DocumentSourceChunk::where('source_id', $source->id)->count()]);
        }

        $this->command->info('Sample document sources seeded successfully!');
    }
}
