<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    private string $localUrl;
    private string $localModel;
    private int $localTimeout;
    private string $geminiApiKey;
    private string $geminiModel;

    public function __construct()
    {
        $this->localUrl = config('services.llm.local_url', 'http://localhost:11434');
        $this->localModel = config('services.llm.local_model', 'llama3.1');
        $this->localTimeout = (int) config('services.llm.local_timeout', 10);
        $this->geminiApiKey = config('services.llm.gemini_api_key', '');
        $this->geminiModel = config('services.llm.gemini_model', 'gemini-2.5-flash');
    }

    /**
     * Send a prompt to the LLM with automatic failover.
     * Primary: local Ollama instance. Failover: Google Gemini API.
     *
     * @param  string  $systemPrompt  The system/instruction prompt
     * @param  string  $userPrompt  The user-supplied content
     * @return array{content: string, provider: string, success: bool, error: string|null}
     */
    public function query(string $systemPrompt, string $userPrompt): array
    {
        // Try local LLM first
        $localResult = $this->queryLocal($systemPrompt, $userPrompt);

        if ($localResult['success']) {
            return $localResult;
        }

        Log::warning('LLM: Local inference failed, failing over to Gemini API.', [
            'error' => $localResult['error'],
        ]);

        // Failover to Gemini
        $geminiResult = $this->queryGemini($systemPrompt, $userPrompt);

        if ($geminiResult['success']) {
            return $geminiResult;
        }

        Log::error('LLM: Both local and Gemini inference failed.', [
            'local_error' => $localResult['error'],
            'gemini_error' => $geminiResult['error'],
        ]);

        // Development fallback: generate mock analysis when both providers fail
        if (app()->environment('local', 'development')) {
            Log::warning('LLM: Both providers failed, using development mock data.');
            return [
                'content' => json_encode([
                    'convergence' => "[Mock Data] Based on the submitted responses, there appears to be general agreement on the core principles. Participants emphasized the need for clear guidelines and structured approaches.",
                    'consideration' => "[Mock Data] Key areas of concern include resource allocation, timeline feasibility, and stakeholder engagement strategies that require further clarification.",
                    'proposals' => "[Mock Data] Recommended actions:\n1. Establish a working group to address identified gaps\n2. Develop a phased implementation plan\n3. Schedule follow-up consultations with key stakeholders",
                ], JSON_PRETTY_PRINT),
                'provider' => 'mock',
                'success' => true,
                'error' => null,
            ];
        }

        return [
            'content' => '',
            'provider' => 'none',
            'success' => false,
            'error' => 'All LLM providers failed. Local: '.$localResult['error'].'. Gemini: '.$geminiResult['error'],
        ];
    }

    /**
     * Query the local Ollama instance.
     */
    private function queryLocal(string $systemPrompt, string $userPrompt): array
    {
        try {
            $response = Http::timeout($this->localTimeout)
                ->post($this->localUrl.'/api/chat', [
                    'model' => $this->localModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'stream' => false,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['message']['content'] ?? '';

                return [
                    'content' => $content,
                    'provider' => 'local',
                    'success' => true,
                    'error' => null,
                ];
            }

            return [
                'content' => '',
                'provider' => 'local',
                'success' => false,
                'error' => 'HTTP '.$response->status().': '.$response->body(),
            ];
        } catch (ConnectionException $e) {
            return [
                'content' => '',
                'provider' => 'local',
                'success' => false,
                'error' => 'Connection refused: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'content' => '',
                'provider' => 'local',
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Query the Google Gemini API.
     */
    private function queryGemini(string $systemPrompt, string $userPrompt): array
    {
        if (empty($this->geminiApiKey)) {
            return [
                'content' => '',
                'provider' => 'gemini',
                'success' => false,
                'error' => 'Gemini API key not configured.',
            ];
        }

        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$this->geminiModel.':generateContent?key='.$this->geminiApiKey;

            $response = Http::timeout(30)->post($url, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $userPrompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                return [
                    'content' => $content,
                    'provider' => 'gemini',
                    'success' => true,
                    'error' => null,
                ];
            }

            return [
                'content' => '',
                'provider' => 'gemini',
                'success' => false,
                'error' => 'HTTP '.$response->status().': '.$response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'content' => '',
                'provider' => 'gemini',
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the system prompt for synthesis extraction.
     */
    public function buildSynthesisPrompt(): string
    {
        return <<<'PROMPT'
You are a synthesis assistant for a consultative process. You will receive one or more consensus paragraphs from group discussions.

Your task is to analyze the text and extract information into exactly three categories:

1. **Convergence (A)**: Points of agreement, shared perspectives, and common ground found across the input.
2. **Matters for Consideration (B)**: Areas of concern, tension, unresolved questions, or topics needing further discussion.
3. **Proposals (C)**: Concrete suggestions, recommendations, or actionable items proposed by the groups.

IMPORTANT: Return your analysis as a RAW JSON object with exactly these three keys. Do NOT wrap the response in markdown code blocks (no ```json or ```). Do NOT include any explanatory text before or after the JSON. The response must be valid JSON only.

{
  "convergence": "...",
  "consideration": "...",
  "proposals": "..."
}

Each value should be a well-structured paragraph or set of bullet points. Be thorough but concise. Preserve the original meaning and intent of the participants.
PROMPT;
    }

    /**
     * Build the system prompt for global/macro synthesis of emerging themes.
     */
    public function buildGlobalSynthesisPrompt(): string
    {
        return <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple synthesis reports, each containing three categories: Convergence, Matters for Consideration, and Proposals.

Your task is to perform a macro-level analysis and identify:

1. **Emerging Themes**: Overarching patterns, recurring ideas, and cross-cutting themes that appear across multiple reports.
2. **Key Convergences**: The strongest points of agreement across all groups.
3. **Critical Considerations**: The most pressing unresolved tensions or concerns.
4. **Priority Proposals**: The most commonly suggested or impactful recommendations.

Return your analysis as a JSON object:
{
  "emerging_themes": "...",
  "key_convergences": "...",
  "critical_considerations": "...",
  "priority_proposals": "..."
}

Be analytical and thorough. This analysis will inform a final synthesis document.
PROMPT;
    }
}
