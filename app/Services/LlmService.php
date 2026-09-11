<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    private string $localUrl;
    private int $localMaxTokens;
    private int $localTimeout;
    private float $localTemperature;
    private int $localMaxCompletionTokens;
    private bool $localReasoning;
    private string $geminiApiKey;
    private string $geminiModel;
    private string $categoryDefinitions;
    private string $outputTone;

    public function __construct()
    {
        $this->localUrl = config('services.llm.local_url', 'http://localhost:8080');
        $this->localMaxTokens = (int) config('services.llm.local_max_tokens', 1024);
        $this->localTimeout = (int) config('services.llm.local_timeout', 60);
        $this->localTemperature = (float) config('services.llm.local_temperature', 0.7);
        $this->localMaxCompletionTokens = (int) config('services.llm.local_max_completion_tokens', 4096);
        $this->localReasoning = (bool) config('services.llm.local_reasoning', false);
        $this->geminiApiKey = config('services.llm.gemini_api_key', '');
        $this->geminiModel = config('services.llm.gemini_model', 'gemini-2.5-flash');

        $this->categoryDefinitions = "1. **Convergence (A)**: compile independent data points, signs, lines of evidence, or causes that contribute to the same effect or outcome, currently or potential occurence.

2. **Matters for Consideration (B)**: a dissenting statement, an alternative prediction, or a  record of totally new and unheard of ideas or new and improved ways of doing existing ideas or objections. It describes the opinion and discernment by members of a group who do not agree with the main or majority decision

3. **Proposals (C)**: an actionable or something clear enough to be put into practical use. Proposals may be explained through steps that are clear and specific enough to act on right away or may be explained as other required action before being able to address the pertaining matter";

        $this->outputTone = "Stick to original vocabulary used by the contents as much as possible with minimal refinements towards Malaysian English, and pastoral language, where applicable. ";
    }

    /**
     * Send a prompt to the LLM with automatic failover.
     * Primary: local llama.cpp instance (OpenAI-compatible API). Failover: Google Gemini API.
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
     * Query the local llama.cpp instance using OpenAI-compatible API format.
     */
    private function queryLocal(string $systemPrompt, string $userPrompt): array
    {
        try {
            Log::info('LLM: Sending request to local llama.cpp.', [
                'url' => $this->localUrl.'/v1/chat/completions',
                'max_tokens' => $this->localMaxTokens,
                'system_prompt_length' => strlen($systemPrompt),
                'user_prompt_length' => strlen($userPrompt),
                'reasoning_enabled' => $this->localReasoning,
            ]);

            $payload = [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => $this->localMaxTokens,
                'temperature' => $this->localTemperature,
                'stream' => false,
            ];

            // Use max_completion_tokens for models that support it (e.g., Qwen3.x)
            if ($this->localMaxCompletionTokens > 0) {
                $payload['max_completion_tokens'] = $this->localMaxCompletionTokens;
            }

            // Explicitly control thinking/reasoning mode for Ollama/llama.cpp
            // Qwen3.6 and similar models have thinking enabled by default - must explicitly disable
            if ($this->localReasoning) {
                $payload['thinking'] = [
                    'enabled' => true,
                    'max_tokens' => $this->localMaxCompletionTokens,
                ];
            } else {
                // Explicitly disable thinking to prevent token waste on reasoning_content
                $payload['thinking'] = [
                    'enabled' => false,
                ];
            }

            $response = Http::timeout($this->localTimeout)
                ->post($this->localUrl.'/v1/chat/completions', $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Log the full response structure for debugging
                Log::info('LLM: Local response received.', [
                    'choices_count' => count($data['choices'] ?? []),
                    'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                    'content_length' => strlen($data['choices'][0]['message']['content'] ?? ''),
                    'content_preview' => substr($data['choices'][0]['message']['content'] ?? '', 0, 200),
                    'has_reasoning_content' => isset($data['choices'][0]['message']['reasoning_content']),
                    'reasoning_content_length' => strlen($data['choices'][0]['message']['reasoning_content'] ?? ''),
                    'full_response' => json_encode($data, JSON_PRETTY_PRINT),
                ]);

                $content = $data['choices'][0]['message']['content'] ?? '';

                return [
                    'content' => $content,
                    'provider' => 'local',
                    'success' => true,
                    'error' => null,
                ];
            }

            Log::error('LLM: Local HTTP error.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'content' => '',
                'provider' => 'local',
                'success' => false,
                'error' => 'HTTP '.$response->status().': '.$response->body(),
            ];
        } catch (ConnectionException $e) {
            Log::error('LLM: Local connection failed.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'content' => '',
                'provider' => 'local',
                'success' => false,
                'error' => 'Connection refused: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('LLM: Local exception.', [
                'error' => $e->getMessage(),
            ]);

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
                    'maxOutputTokens' => $this->localMaxTokens,
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
    /**
     * Build the system prompt for synthesis extraction.
     */
    public function buildSynthesisPrompt(): string
    {
        return <<<PROMPT
You are a synthesis assistant for a consultative process. You will receive one or more consensus paragraphs from group discussions.

Your task is to analyze the text and extract information into exactly three categories. For each category, provide the content as bullet points using dashes (-) with concise phrases (not full sentences).

====== CRITICAL JSON FORMAT REQUIREMENTS ======
- Return a RAW JSON object with exactly these three keys: "convergence", "consideration", "proposals"
- Each value must be a SINGLE LINE string (no actual newlines inside the string values)
- Use \n (backslash-n escape sequence) to separate bullet points within each value
- Keep the entire JSON on multiple lines for readability, but each value must be on one line

Example of CORRECT format:
{
  "convergence": "- phrase one\n- phrase two\n- phrase three",
  "consideration": "- concern one\n- concern two",
  "proposals": "- suggestion one\n- suggestion two"
}

WRONG format (do NOT do this):
{
  "convergence": "- phrase one
- phrase two
- phrase three"
}

====== END OF CRITICAL JSON FORMAT REQUIREMENTS ======

====== CATEGORIES AND DEFINITIONS ========

$this->categoryDefinitions

====== END OF CATEGORIES AND DEFINITIONS. =======

====== OUTPUT LANGUAGE AND TONE ======

$this->outputTone

====== END OF OUTPUT LANGUAGE AND TONE ======

====== IMPORTANT ====== 

Do NOT wrap the response in markdown code blocks (no ```json or ```). Do NOT include any explanatory text before or after the JSON. The response must be valid JSON only. Each value string must contain \n escape sequences, not actual newlines.

Be concise but thorough. Use full sentences. Preserve the original meaning and intent of the participants.

====== END OF IMPORTANT ======

PROMPT;
    }

    /**
     * Build the system prompt for compiling consensus notes into coherent prose paragraphs.
     */
    public function buildNotesSynthesisPrompt(): string
    {
        return <<<PROMPT
You are a synthesis writer for a consultative process. You will receive a set of personal notes written by a synthesis member for different groups.

Your task is to compile and weave all of these notes into four coherent prose paragraphs — one for each of the four synthesis categories below. You are NOT analyzing or categorizing the notes — you are taking all the raw content and writing it up as flowing, readable prose, organized into the appropriate category.

CATEGORIES AND DEFINITIONS:

$this->categoryDefinitions

CATEGORY ACTIONS:
1. "convergence" — Compile all content from the notes that relates to points of agreement, shared views, or common ground. Write it as one coherent paragraph in full sentences.
2. "consideration" — Compile all content that relates to concerns, tensions, challenges, or unresolved issues. Write it as one coherent paragraph in full sentences.
3. "proposals" — Compile all content that relates to recommendations, suggestions, or proposed actions. Write it as one coherent paragraph in full sentences.
4. "others" — Compile any remaining content that does not clearly fit the above three categories. Write it as one coherent paragraph in full sentences.

OUTPUT LANGUAGE AND TONE:

$this->outputTone

EXAMPLE:
{
  "convergence": "Participants widely agreed that community involvement is foundational to any effective pastoral initiative. There was broad alignment on the need for open dialogue and continued engagement across all levels of the community.",
  "consideration": "A number of important concerns emerged regarding the pace of implementation and the availability of adequate resources. Participants noted that equitable access must be carefully considered as the process moves forward.",
  "proposals": "It was suggested that a dedicated working group be formed to oversee next steps and ensure accountability. Regular consultations with stakeholders should be scheduled to maintain transparency and shared ownership of the process.",
  "others": "Several notes expressed a spirit of hope and pastoral generosity, with many participants affirming their commitment to walking this journey together with openness and discernment."
}

Return valid JSON only. No bullet points. No lists. Only prose.
PROMPT;
    }

    /**
     * Build the system prompt for custom user queries.
     */
    public function buildCustomQueryPrompt(): string
    {
        return <<<PROMPT
You are a synthesis assistant for a consultative process. You will receive one or more consensus paragraphs from group discussions as context, followed by a specific question or prompt from the user.

Your task is to answer the user's question based on the provided context. Structure your response as bullet points.

Return your response as a JSON object with a single key "points" containing an array of strings:
{
  "points": [
    "First bullet point",
    "Second bullet point",
    "Third bullet point"
  ]
}

OUTPUT LANGUAGE AND TONE:

$this->outputTone

IMPORTANT: Do NOT wrap the response in markdown code blocks. Return valid JSON only.
Be concise but thorough. Base your analysis on the provided group responses.
PROMPT;
    }

    /**
     * Build the system prompt for global/macro synthesis of emerging themes.
     */
    public function buildGlobalSynthesisPrompt(): string
    {
        return <<<PROMPT
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

OUTPUT LANGUAGE AND TONE:

$this->outputTone

Be analytical and thorough. This analysis will inform a final synthesis document.
PROMPT;
    }

    /**
     * Generate text using the LLM with a default system prompt.
     * Convenience wrapper around query() for single-turn text generation.
     *
     * @param  string  $userPrompt  The user-supplied content/prompt
     * @return string The LLM's response content
     */
    public function generateText(string $userPrompt): string
    {
        $result = $this->query(
            'You are a helpful, informative assistant. Respond with accurate, well-structured information.',
            $userPrompt
        );

        return $result['content'];
    }

    /**
     * Generate a chat response based on user message and context from submitted consensus responses.
     *
     * @param  string  $userMessage  The admin's question/message
     * @param  string  $context  All submitted consensus responses formatted as text
     * @return string The LLM's response
     */
    public function generateChatResponse(string $userMessage, string $context): string
    {
        $prompt = 'You are a helpful assistant analyzing public consultation responses. You have been provided with the following consensus submissions from various groups during a consultation session.'."\n\n"
            .'=== SUBMITTED CONSENSUS RESPONSES ==='."\n\n"
            .$context."\n\n"
            .'=== END OF SUBMISSIONS ==='."\n\n"
            .'Based on the submitted responses above, please answer the following question:'."\n\n"
            .$userMessage."\n\n"
            .'Please provide a helpful, informative response that synthesizes the relevant information from the submissions. Be specific and reference key points where appropriate.';

        // Use existing generateText() method with the built prompt
        return $this->generateText($prompt);
    }

    /**
     * Build the system prompt for booklet generation with document source integration.
     */
    public function buildBookletGenerationPrompt(string $synthesisContent, string $retrievedDocuments): string
    {
        return <<<PROMPT
You are generating a final synthesis booklet for a consultative process. You have already completed the synthesis analysis and now need to enhance the document by citing and quoting from authoritative source documents.

=== SYNTHESIS CONTENT ===
{$synthesisContent}

=== RETRIEVED SOURCE DOCUMENTS ===
{$retrievedDocuments}

INSTRUCTIONS:
1. Where relevant, cite and quote from the retrieved source documents to support your synthesis points.
2. Use proper citation format: [Document Title, Page X] or [Document Title, Section Y].
3. Quote directly when the original wording is particularly powerful or precise.
4. Paraphrase when appropriate, but maintain the original meaning.
5. Do NOT force citations where they don't naturally fit.
6. Prioritize citations from authoritative documents (encyclicals, apostolic exhortations) over conference documents.

IMPORTANT: Only cite documents that are actually relevant to your content. Do not fabricate citations or page numbers.
PROMPT;
    }

    /**
     * Generate a synthesis booklet with document source citations.
     */
    public function generateBooklet(string $synthesisContent, ?string $documentContext = null): array
    {
        if ($documentContext) {
            // Build prompt with document source integration
            $systemPrompt = $this->buildBookletGenerationPrompt($synthesisContent, $documentContext);
        } else {
            // Use standard synthesis prompt without document sources
            $systemPrompt = <<<PROMPT
You are generating a final synthesis booklet for a consultative process. Analyze the provided synthesis content and produce a well-structured final document.

=== INSTRUCTIONS ===
1. Organize the content into clear sections: Emerging Themes, Key Convergences, Critical Considerations, Priority Proposals
2. Use professional, formal language appropriate for official documentation
3. Preserve the original meaning and intent of all contributions
4. Highlight areas of consensus while acknowledging differences
5. Provide actionable recommendations where appropriate

Return your response as a structured markdown document with clear headings and sections.
PROMPT;
        }

        return $this->query(
            $systemPrompt,
            'Please generate the synthesis booklet based on the provided content.'
        );
    }

    /**
     * Build the system prompt for extracting key points from synthesis content.
     */
    public function buildKeyPointsExtractionPrompt(int $maxKeyPoints): string
    {
        return <<<PROMPT
You are a synthesis analyst. You will receive synthesis content from a consultative process and need to extract the most important key points.

CRITICAL INSTRUCTIONS:
1. Extract at most {$maxKeyPoints} key points (no more, no less if possible).
2. Each key point must be on its own line starting with a number followed by a period and a space (e.g., "1. ", "2. ", "3. ").
3. Keep each key point concise - one line only, no line breaks within a point.
4. Focus on the most significant insights, agreements, and actionable items.
5. Do NOT include any introductory text, conclusions, or explanations.
6. Return ONLY the numbered key points, nothing else.

Example format:
1. Point one about convergence
2. Point two about considerations
3. Point three about proposals

OUTPUT LANGUAGE AND TONE:

$this->outputTone

Return only the numbered key points, one per line. No dashes, no extra text.
PROMPT;
    }

    /**
     * Extract key points from synthesis content using the LLM.
     *
     * @param  string  $synthesisContent  The master synthesis content to extract from
     * @param  int  $maxKeyPoints  Maximum number of key points to extract
     * @return array{content: string, provider: string, success: bool, error: string|null}
     */
    public function extractKeyPoints(string $synthesisContent, int $maxKeyPoints): array
    {
        $systemPrompt = $this->buildKeyPointsExtractionPrompt($maxKeyPoints);
        $userPrompt = "Extract key points from the following synthesis content:\n\n{$synthesisContent}";

        return $this->query($systemPrompt, $userPrompt);
    }
}
