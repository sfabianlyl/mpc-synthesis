<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->title ?? 'Synthesis Booklet' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            color: #1a1a1a;
            line-height: 1.8;
        }
        
        /* Cover Page */
        .cover-page {
            text-align: center;
            margin-bottom: 60px;
            padding: 80px 0;
            border-bottom: 3px double #333;
        }
        .cover-page h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        .cover-page .subtitle {
            font-size: 1.2em;
            color: #555;
            font-style: italic;
        }
        .cover-page .date {
            margin-top: 30px;
            color: #777;
        }
        
        /* Event Information */
        .event-info {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 20px;
            margin-bottom: 40px;
            border-radius: 0 8px 8px 0;
        }
        .event-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .event-info .detail {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
            font-size: 0.95em;
        }
        .event-info .label {
            font-weight: bold;
            color: #555;
            min-width: 80px;
        }
        
        /* Headings */
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
            margin-top: 40px;
            margin-bottom: 20px;
        }
        h3 {
            color: #34495e;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        h4 {
            color: #444;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        /* Sections */
        .section {
            margin-bottom: 30px;
        }
        
        /* Category Labels */
        .category-label {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .convergence { background: #d4edda; color: #155724; }
        .consideration { background: #fff3cd; color: #856404; }
        .proposals { background: #cce5ff; color: #004085; }
        .others { background: #e2d5f1; color: #6f42c1; }
        
        /* Conversation Section */
        .conversation-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .conversation-title {
            font-size: 1.4em;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-left: 15px;
            border-left: 4px solid #3498db;
        }
        
        /* Master Synthesis Block */
        .master-synthesis-block {
            margin-bottom: 25px;
        }
        .master-synthesis-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        /* Content Blocks */
        .content-block {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 6px;
        }
        .content-block.ai-content {
            background: #f0f7ff;
            border: 1px solid #bdd9f0;
        }
        .content-block.manual-content {
            background: #f0faf0;
            border: 1px solid #b2dfb2;
        }
        .content-block.mixed-content {
            background: #fafafa;
            border: 1px solid #e0e0e0;
        }
        
        /* Source Indicators */
        .source-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: 500;
        }
        .source-indicator.ai {
            background: #e8f4fd;
            color: #2980b9;
        }
        .source-indicator.manual {
            background: #e8f8e8;
            color: #27ae60;
        }
        .source-indicator.hybrid {
            background: #f0e6f6;
            color: #8e44ad;
        }
        
        /* Group Reports */
        .group-report {
            border-left: 4px solid #3498db;
            padding-left: 20px;
            margin: 20px 0;
        }
        
        /* Citation References */
        .citation-section {
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px dashed #ccc;
        }
        .citation-reference {
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 4px;
            font-size: 0.9em;
            border-left: 3px solid #95a5a6;
        }
        .citation-reference .doc-name {
            font-weight: bold;
            color: #2c3e50;
        }
        .citation-reference .page-ref {
            color: #7f8c8d;
            font-style: italic;
        }
        
        /* Bullet Lists */
        .content-block ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        .content-block li {
            margin-bottom: 5px;
        }
        .content-block li::marker {
            color: #3498db;
        }
        
        /* Page Breaks */
        .page-break {
            page-break-before: always;
        }
        .section-break {
            page-break-before: auto;
            margin-top: 30px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 0.9em;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-submitted { background: #d4edda; color: #155724; }
        .status-draft { background: #fff3cd; color: #856404; }
        
        /* AI Badge */
        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #e8f4fd;
            color: #2980b9;
            border-radius: 3px;
            font-size: 0.8em;
        }
        
        /* Key Points Section */
        .key-points-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .key-points-section h2 {
            color: white !important;
            border-bottom: 2px solid rgba(255,255,255,0.3) !important;
            margin-top: 0 !important;
        }
        .key-point-item {
            background: rgba(255,255,255,0.1);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid rgba(255,255,255,0.5);
        }
        .key-point-item:last-child {
            margin-bottom: 0;
        }
        
        /* Print styles for PDF export */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                max-width: 100%;
            }
            .page-break {
                page-break-before: always;
            }
            .section-break {
                page-break-inside: avoid;
            }
            @page {
                margin: 2cm;
            }
        }
        </style>
</head>
<body>
    {{-- Cover Page --}}
    <div class="cover-page">
        <h1>Synthesis Booklet</h1>
        <div class="subtitle">{{ $document->title ?? 'Consolidated Synthesis Report' }}</div>
        <div class="date">Generated on {{ now()->format('F j, Y') }}</div>
    </div>

    {{-- Event Information --}}
    @if(isset($event) && $event)
    <div class="event-info">
        <h3>Event Information</h3>
        <div class="detail">
            <span class="label">Name:</span>
            <span>{{ $event->title }}</span>
        </div>
        @if($event->location)
        <div class="detail">
            <span class="label">Location:</span>
            <span>{{ $event->location }}</span>
        </div>
        @endif
        <div class="detail">
            <span class="label">Date:</span>
            <span>{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('F j, Y') : 'N/A' }} 
            @if($event->end_date) - {{ \Carbon\Carbon::parse($event->end_date)->format('F j, Y') }}@endif</span>
        </div>
    </div>
    @endif

    {{-- Emerging Themes (from global synthesis document) --}}
    @if($document->content_json || $document->emerging_themes)
    <div class="section">
        <h2>Executive Summary</h2>
        
        @if($document->emerging_themes)
        <h3>Emerging Themes</h3>
        <p>{{ $document->emerging_themes }}</p>
        @endif

        @if(!empty($document->content_json))
            @php $json = is_string($document->content_json) ? json_decode($document->content_json, true) : $document->content_json; @endphp
            
            @if(!empty($json['key_convergences']))
            <h3>Key Convergences</h3>
            <p>{{ $json['key_convergences'] }}</p>
            @endif

            @if(!empty($json['critical_considerations']))
            <h3>Critical Considerations</h3>
            <p>{{ $json['critical_considerations'] }}</p>
            @endif

            @if(!empty($json['priority_proposals']))
            <h3>Priority Proposals</h3>
            <p>{{ $json['priority_proposals'] }}</p>
            @endif
        @endif
    </div>
    @endif

    {{-- Key Points Section --}}
    @if(isset($keyPoints) && $keyPoints->count() > 0)
    <div class="section-break"></div>
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px;">
        <h2 style="color: white !important; border-bottom: 2px solid rgba(255,255,255,0.3) !important; padding-bottom: 10px; margin-top: 0; margin-bottom: 15px;">📋 Key Points Summary</h2>
        <p style="margin-bottom: 20px; opacity: 0.9;">The following key points were extracted from the synthesis conversation using AI analysis:</p>
        
        @foreach($keyPoints as $keyPoint)
            <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                <div style="font-size: 0.85em; opacity: 0.8; margin-bottom: 10px;">
                    Extracted on {{ \Carbon\Carbon::parse($keyPoint->created_at)->format('M j, Y g:i A') }}
                    @if($keyPoint->provider)
                        · via {{ $keyPoint->provider }}
                    @endif
                </div>
                @if(!empty($keyPoint->key_points) && is_array($keyPoint->key_points))
                    @foreach($keyPoint->key_points as $index => $point)
                        @if(is_string($point) && trim($point) !== '')
                        <div style="background: rgba(255,255,255,0.1); padding: 12px 15px; border-radius: 6px; margin-bottom: 8px; border-left: 4px solid rgba(255,255,255,0.5);">
                            <span style="font-weight: bold; margin-right: 8px;">{{ $index + 1 }}.</span>
                            {{ trim($point) }}
                        </div>
                        @endif
                    @endforeach
                @elseif(!empty($keyPoint->key_points_text) && trim($keyPoint->key_points_text) !== '')
                    <div style="background: rgba(255,255,255,0.1); padding: 12px 15px; border-radius: 6px; border-left: 4px solid rgba(255,255,255,0.5);">
                        {!! nl2br(e(trim($keyPoint->key_points_text))) !!}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    {{-- Master Syntheses by Conversation --}}
    <div class="section-break"></div>
    <h2>Detailed Syntheses by Conversation</h2>

    @if(isset($masterSyntheses) && $masterSyntheses->count() > 0)
        @foreach($masterSyntheses as $masterSynthesis)
        <div class="conversation-section">
            <h3 class="conversation-title">
                Conversation #{{ $masterSynthesis->conversation_id }}
                <span class="status-badge {{ $masterSynthesis->status === 'submitted' ? 'status-submitted' : 'status-draft' }}">
                    {{ ucfirst($masterSynthesis->status) }}
                </span>
                @if($masterSynthesis->is_llm_assisted)
                <span class="ai-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                    AI-Assisted
                </span>
                @endif
            </h3>

            {{-- Category Content --}}
            @php
                $categories = [
                    [
                        'key' => 'convergence',
                        'label' => 'Convergence',
                        'class' => 'convergence',
                        'description' => 'Points of agreement across all groups'
                    ],
                    [
                        'key' => 'consideration',
                        'label' => 'Matters for Consideration',
                        'class' => 'consideration',
                        'description' => 'Critical issues requiring attention'
                    ],
                    [
                        'key' => 'proposals',
                        'label' => 'Proposals',
                        'class' => 'proposals',
                        'description' => 'Actionable recommendations and suggestions'
                    ],
                    [
                        'key' => 'others',
                        'label' => 'Others',
                        'class' => 'others',
                        'description' => 'Additional insights and observations'
                    ],
                ];
            @endphp

            @foreach($categories as $cat)
                @php
                    $aiField = 'ai_' . $cat['key'] . '_text';
                    $manualField = $cat['key'] . '_text';
                    $aiContent = $masterSynthesis->$aiField ?? null;
                    $manualContent = $masterSynthesis->$manualField ?? null;
                @endphp

                @if($aiContent || $manualContent)
                <div class="master-synthesis-block">
                    <div class="master-synthesis-header">
                        <span class="category-label {{ $cat['class'] }}">{{ $cat['label'] }}</span>
                        <span style="font-size: 0.9em; color: #666;">{{ $cat['description'] }}</span>
                    </div>

                    {{-- AI-Generated Reference Content --}}
                    @if($aiContent && trim($aiContent))
                    <div class="content-block ai-content">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span class="source-indicator ai">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="10" rx="2"/>
                                    <circle cx="12" cy="5" r="2"/>
                                    <path d="M12 7v4"/>
                                </svg>
                                AI-Generated Reference
                            </span>
                        </div>
                        @php
                            $aiLines = explode("\n", trim($aiContent));
                            if (count(array_filter($aiLines, fn($l) => trim($l))) > 1) {
                                $isList = true;
                            } else {
                                $isList = false;
                            }
                        @endphp
                        @if($isList)
                        <ul>
                            @foreach($aiLines as $line)
                                @if(trim($line))
                                <li>{{ preg_replace('/^-+\s*/', '', trim($line)) }}</li>
                                @endif
                            @endforeach
                        </ul>
                        @else
                        <p>{{ trim($aiContent) }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- Manual Content --}}
                    @if($manualContent && trim($manualContent))
                    <div class="content-block manual-content">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span class="source-indicator manual">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                                    <path d="m15 5 4 4"/>
                                </svg>
                                Master Content (Manually Crafted)
                            </span>
                        </div>
                        @php
                            $manualLines = explode("\n", trim($manualContent));
                            if (count(array_filter($manualLines, fn($l) => trim($l))) > 1) {
                                $isList = true;
                            } else {
                                $isList = false;
                            }
                        @endphp
                        @if($isList)
                        <ul>
                            @foreach($manualLines as $line)
                                @if(trim($line))
                                <li>{{ preg_replace('/^-+\s*/', '', trim($line)) }}</li>
                                @endif
                            @endforeach
                        </ul>
                        @else
                        <p>{{ trim($manualContent) }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- Mixed (both AI and manual) --}}
                    @if($aiContent && $manualContent && trim($aiContent) && trim($manualContent))
                    <div style="margin-top: 5px;">
                        <span class="source-indicator hybrid">Hybrid: AI reference + Manual edits</span>
                    </div>
                    @endif
                </div>
                @endif
            @endforeach
        </div>
        @endforeach
    @elseif(isset($reports) && $reports->count() > 0)
        {{-- Fallback to individual reports if no master syntheses --}}
        <div class="section-break"></div>
        <h2>Individual Group Reports</h2>

        @foreach($reports as $report)
        <div class="group-report">
            <h3>{{ $report->conversation->title ?? 'Report #' . $report->id }}</h3>
            <p><em>Prepared by: {{ $report->synthesis->name ?? 'Unknown' }}</em></p>
            
            @if($report->is_llm_assisted || $report->llm_generated)
            <span class="ai-badge" style="margin-left: 10px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    <path d="M2 17l10 5 10-5"/>
                    <path d="M2 12l10 5 10-5"/>
                </svg>
                AI-Assisted
            </span>
            @endif

            <div class="section">
                <span class="category-label convergence">Convergence</span>
                <div class="{{ $report->ai_convergence_text ? 'content-block ai-content' : 'content-block mixed-content' }}">
                    {{ $report->convergence_text ?: ($report->ai_convergence_text ?: 'Not yet completed.') }}
                </div>
            </div>

            <div class="section">
                <span class="category-label consideration">Matters for Consideration</span>
                <div class="{{ $report->ai_consideration_text ? 'content-block ai-content' : 'content-block mixed-content' }}">
                    {{ $report->consideration_text ?: ($report->ai_consideration_text ?: 'Not yet completed.') }}
                </div>
            </div>

            <div class="section">
                <span class="category-label proposals">Proposals</span>
                <div class="{{ $report->ai_proposals_text ? 'content-block ai-content' : 'content-block mixed-content' }}">
                    {{ $report->proposals_text ?: ($report->ai_proposals_text ?: 'Not yet completed.') }}
                </div>
            </div>

            @if($report->other_text || ($report instanceof \App\Models\MasterSynthesis && property_exists($report, 'ai_other_text') && $report->ai_other_text))
            <div class="section">
                <span class="category-label others">Others</span>
                <div class="{{ $report->ai_other_text ? 'content-block ai-content' : 'content-block mixed-content' }}">
                    {{ $report->other_text ?: ($report->ai_other_text ?? 'Not yet completed.') }}
                </div>
            </div>
            @endif
        </div>
        @endforeach
    @endif

    {{-- Citation References from Document Chunks --}}
    @if(isset($documentChunks) && $documentChunks->count() > 0)
    <div class="page-break"></div>
    <div class="citation-section">
        <h3 style="margin-top: 0;">Citation References</h3>
        <p style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
            The following document sources were retrieved and referenced during synthesis generation:
        </p>
        
        @foreach($documentChunks as $chunk)
        <div class="citation-reference">
            <div>
                <span class="doc-name">{{ $chunk->source?->document_name ?? 'Unknown Document' }}</span>
                @if($chunk->section_heading)
                <span style="color: #666;"> — {{ $chunk->section_heading }}</span>
                @endif
            </div>
            @if($chunk->page_start || $chunk->page_end)
            <div class="page-ref">
                Pages {{ $chunk->page_start ?? '?' }}@if($chunk->page_end && $chunk->page_end !== $chunk->page_start)-{{ $chunk->page_end }}@endif
            </div>
            @endif
            <div style="margin-top: 5px; font-size: 0.85em;">
                {{ Str::limit($chunk->chunk_content, 200) }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <p>MPC Synthesis &mdash; Confidential Document</p>
        <p>{{ now()->format('Y') }}</p>
    </div>
</body>
</html>
