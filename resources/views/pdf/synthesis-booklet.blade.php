<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->title ?? 'Synthesis Booklet' }}</title>
    <style>
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            color: #1a1a1a;
            line-height: 1.8;
        }
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
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
            margin-top: 40px;
        }
        h3 {
            color: #34495e;
            margin-top: 25px;
        }
        .section {
            margin-bottom: 30px;
        }
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
        .group-report {
            border-left: 4px solid #3498db;
            padding-left: 20px;
            margin: 20px 0;
        }
        .page-break {
            page-break-before: always;
        }
        .footer {
            text-align: center;
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="cover-page">
        <h1>Synthesis Booklet</h1>
        <div class="subtitle">{{ $document->title }}</div>
        <div class="date">Generated on {{ now()->format('F j, Y') }}</div>
    </div>

    @if($document->content_json)
        <div class="section">
            <h2>Emerging Themes</h2>
            <p>{{ $document->content_json['emerging_themes'] ?? $document->emerging_themes ?? 'No emerging themes identified.' }}</p>
        </div>

        @if(!empty($document->content_json['key_convergences']))
            <div class="section">
                <h2>Key Convergences</h2>
                <p>{{ $document->content_json['key_convergences'] }}</p>
            </div>
        @endif

        @if(!empty($document->content_json['critical_considerations']))
            <div class="section">
                <h2>Critical Considerations</h2>
                <p>{{ $document->content_json['critical_considerations'] }}</p>
            </div>
        @endif

        @if(!empty($document->content_json['priority_proposals']))
            <div class="section">
                <h2>Priority Proposals</h2>
                <p>{{ $document->content_json['priority_proposals'] }}</p>
            </div>
        @endif
    @endif

    <div class="page-break"></div>
    <h2>Individual Group Reports</h2>

    @foreach($reports as $report)
        <div class="group-report">
            <h3>{{ $report->conversation->title ?? 'Report #' . $report->id }}</h3>
            <p><em>Prepared by: {{ $report->synthesis->name ?? 'Unknown' }}</em></p>

            <div class="section">
                <span class="category-label convergence">Convergence</span>
                <p>{{ $report->convergence_text ?: 'Not yet completed.' }}</p>
            </div>

            <div class="section">
                <span class="category-label consideration">Matters for Consideration</span>
                <p>{{ $report->consideration_text ?: 'Not yet completed.' }}</p>
            </div>

            <div class="section">
                <span class="category-label proposals">Proposals</span>
                <p>{{ $report->proposals_text ?: 'Not yet completed.' }}</p>
            </div>
        </div>
    @endforeach

    <div class="footer">
        <p>MPC Synthesis &mdash; Confidential Document</p>
        <p>{{ now()->format('Y') }}</p>
    </div>
</body>
</html>
