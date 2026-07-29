<?php

namespace App\Jobs;

use App\Models\MasterSynthesis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $masterSynthesisId;

    /**
     * Create a new job instance.
     */
    public function __construct(MasterSynthesis $masterSynthesis)
    {
        $this->masterSynthesisId = $masterSynthesis->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $masterSynthesis = MasterSynthesis::findOrFail($this->masterSynthesisId);

        // Generate all report types
        $this->generateBooklet($masterSynthesis);
        $this->generateInfographic($masterSynthesis);
        $this->generateSlides($masterSynthesis);
        $this->generateTables($masterSynthesis);
    }

    /**
     * Generate Booklet (PDF).
     */
    private function generateBooklet(MasterSynthesis $masterSynthesis): void
    {
        try {
            // Load the existing PDF view for synthesis booklet
            $html = view('pdf.synthesis-booklet', [
                'document' => (object)[
                    'title' => $masterSynthesis->conversation?->title . ' — Synthesis Booklet',
                    'content_json' => null,
                ],
                'reports' => collect([$masterSynthesis]),
            ])->render();

            // Generate PDF using DomPDF
            $pdf = Pdf::loadHTML($html);
            $filename = 'synthesis-booklet-' . $this->masterSynthesisId . '-' . now()->format('Y-m-d-H-i-s') . '.pdf';
            $path = 'reports/booklets/' . $filename;

            Storage::disk('public')->put($path, $pdf->download($filename)->getContent());

            Log::info('ReportGenerationJob: Booklet generated successfully.', [
                'master_synthesis_id' => $this->masterSynthesisId,
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            Log::error('ReportGenerationJob: Booklet generation failed.', [
                'error' => $e->getMessage(),
                'master_synthesis_id' => $this->masterSynthesisId,
            ]);
        }
    }

    /**
     * Generate Infographic (stub).
     */
    private function generateInfographic(MasterSynthesis $masterSynthesis): void
    {
        // TODO: Implement infographic generation
        // This could use a library like GD, Imagick, or a third-party API
        Log::info('ReportGenerationJob: Infographic generation is a stub.', [
            'master_synthesis_id' => $this->masterSynthesisId,
        ]);
    }

    /**
     * Generate Slides (stub).
     */
    private function generateSlides(MasterSynthesis $masterSynthesis): void
    {
        // TODO: Implement slides generation
        // This could use a library like PhpPresentation or export to PowerPoint format
        Log::info('ReportGenerationJob: Slides generation is a stub.', [
            'master_synthesis_id' => $this->masterSynthesisId,
        ]);
    }

    /**
     * Generate Tables (stub).
     */
    private function generateTables(MasterSynthesis $masterSynthesis): void
    {
        // TODO: Implement tables generation
        // This could use a library like PhpSpreadsheet to generate Excel/CSV files
        Log::info('ReportGenerationJob: Tables generation is a stub.', [
            'master_synthesis_id' => $this->masterSynthesisId,
        ]);
    }
}
