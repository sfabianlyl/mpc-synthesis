<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $provider;
    private string $model;
    private int $dimension;
    private string $apiKey;
    private string $localUrl;

    public function __construct()
    {
        $this->provider = config('services.embedding.provider', 'mock');
        $this->model = config('services.embedding.model', 'text-embedding-3-small');
        $this->dimension = (int) config('services.embedding.dimension', 1536);
        $this->apiKey = config('services.embedding.api_key', '');
        $this->localUrl = config('services.embedding.local_url', 'http://localhost:8080/v1/embeddings');
    }

    /**
     * Generate an embedding for a single text.
     *
     * @return float[]|null Array of floats representing the embedding vector
     */
    public function generate(string $text): ?array
    {
        return match ($this->provider) {
            'openai' => $this->generateViaOpenAI($text),
            'local' => $this->generateViaLocal($text),
            default => $this->generateMock($text),
        };
    }

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @return array<int, float[]|null> Array of embeddings (null for failed generations)
     */
    public function batchGenerate(array $texts): array
    {
        return array_map(fn ($text) => $this->generate($text), $texts);
    }

    /**
     * Generate embedding via OpenAI API.
     */
    protected function generateViaOpenAI(string $text): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('EmbeddingService: OpenAI API key not configured, falling back to mock.');

            return $this->generateMock($text);
        }

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $this->dimension,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'][0]['embedding'] ?? null;
            }

            Log::error('EmbeddingService: OpenAI embedding failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->generateMock($text);
        } catch (ConnectionException $e) {
            Log::warning('EmbeddingService: OpenAI connection failed, falling back to mock.', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateMock($text);
        } catch (\Exception $e) {
            Log::error('EmbeddingService: OpenAI exception.', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateMock($text);
        }
    }

    /**
     * Generate embedding via local Ollama/llama.cpp endpoint.
     */
    protected function generateViaLocal(string $text): ?array
    {
        try {
            $response = Http::timeout(30)->post($this->localUrl, [
                'model' => config('services.llm.gemini_model', 'nomic-embed-text'),
                'input' => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Handle both OpenAI-compatible and Ollama response formats
                return $data['data'][0]['embedding'] ?? $data['embedding'] ?? null;
            }

            Log::warning('EmbeddingService: Local embedding failed, falling back to mock.', [
                'status' => $response->status(),
            ]);

            return $this->generateMock($text);
        } catch (ConnectionException $e) {
            Log::warning('EmbeddingService: Local connection failed, falling back to mock.', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateMock($text);
        } catch (\Exception $e) {
            Log::error('EmbeddingService: Local embedding exception.', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateMock($text);
        }
    }

    /**
     * Generate a mock embedding (for development/local environment).
     * Creates a deterministic vector based on text hash for consistency.
     */
    protected function generateMock(string $text): array
    {
        // Generate a pseudo-random but deterministic embedding based on text content
        $hash = md5($text);
        $embedding = [];

        for ($i = 0; $i < $this->dimension; $i += 8) {
            $byte = hexdec(substr($hash, ($i % 32), 2));
            // Normalize to [-1, 1] range and apply slight normalization
            $value = ($byte / 127.5) - 1.0;
            $embedding[] = $value;

            if ($i + 7 < $this->dimension) {
                for ($j = 1; $j <= 7; $j++) {
                    $byte2 = hexdec(substr($hash, (($i + $j) % 32), 2));
                    $embedding[] = ($byte2 / 127.5) - 1.0;
                }
            }
        }

        // L2 normalize the embedding
        $magnitude = sqrt(array_sum(array_map(fn ($v) => $v * $v, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(fn ($v) => $v / $magnitude, $embedding);
        }

        return array_slice($embedding, 0, $this->dimension);
    }

    /**
     * Get the current provider name.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the embedding dimension.
     */
    public function getDimension(): int
    {
        return $this->dimension;
    }
}
