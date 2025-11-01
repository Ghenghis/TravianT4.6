<?php

namespace Services;

class LLMIntegrationService
{
    private $llmConfig = [];
    private $isAvailableCache = null;
    private $lastAvailabilityCheck = null;
    private $circuitBreakerState = 'closed';
    private $circuitBreakerFailures = 0;
    private $circuitBreakerLastFailure = null;
    private $responseCache = [];
    private $metrics = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'cache_hits' => 0,
        'total_response_time' => 0,
        'total_tokens' => 0,
        'gpu_utilization' => []
    ];

    const CIRCUIT_BREAKER_THRESHOLD = 5;
    const CIRCUIT_BREAKER_TIMEOUT = 60;
    const MAX_CACHE_SIZE = 1000;
    const CACHE_TTL = 3600;

    public function __construct()
    {
        $this->loadConfiguration();
        $this->verifyGPUAvailability();
    }

    private function loadConfiguration(): void
    {
        $backend = getenv('LLM_BACKEND') ?: 'ollama';
        
        $this->llmConfig = [
            'backend' => $backend,
            'endpoint' => $this->getEndpoint($backend),
            'model' => getenv('LLM_MODEL') ?: $this->getDefaultModel($backend),
            'api_key' => getenv('LLM_API_KEY') ?: '',
            'max_tokens' => (int)(getenv('LLM_MAX_TOKENS') ?: 200),
            'temperature' => (float)(getenv('LLM_TEMPERATURE') ?: 0.7),
            'timeout' => (int)(getenv('LLM_TIMEOUT') ?: 5),
            'retry_attempts' => (int)(getenv('LLM_RETRY_ATTEMPTS') ?: 3),
            'retry_delay' => (int)(getenv('LLM_RETRY_DELAY') ?: 1000),
            'cuda_device' => getenv('LLM_CUDA_DEVICE') ?: '0',
            'gpu_memory_limit' => getenv('LLM_GPU_MEMORY_LIMIT') ?: '8GB',
            'enable_cache' => filter_var(getenv('LLM_ENABLE_CACHE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'enable_metrics' => filter_var(getenv('LLM_ENABLE_METRICS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'fallback_to_cpu' => filter_var(getenv('LLM_FALLBACK_CPU') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'connection_pool_size' => (int)(getenv('LLM_POOL_SIZE') ?: 5),
            'gpu_type' => getenv('LLM_GPU_TYPE') ?: 'auto'
        ];
    }

    private function getEndpoint(string $backend): string
    {
        if ($customEndpoint = getenv('LLM_ENDPOINT')) {
            return $customEndpoint;
        }

        $endpoints = [
            'ollama' => 'http://localhost:11434/api/generate',
            'lm_studio' => 'http://localhost:1234/v1/completions',
            'vllm' => 'http://localhost:8000/v1/completions',
            'text_generation_webui' => 'http://localhost:5000/api/v1/generate',
            'openai' => 'https://api.openai.com/v1/completions'
        ];

        return $endpoints[$backend] ?? $endpoints['ollama'];
    }

    private function getDefaultModel(string $backend): string
    {
        $models = [
            'ollama' => 'llama2:7b',
            'lm_studio' => 'llama-2-7b-chat',
            'vllm' => 'meta-llama/Llama-2-7b-chat-hf',
            'text_generation_webui' => 'llama-2-7b-chat',
            'openai' => 'gpt-3.5-turbo-instruct'
        ];

        return $models[$backend] ?? 'llama2';
    }

    private function verifyGPUAvailability(): void
    {
        if (!$this->llmConfig['fallback_to_cpu']) {
            return;
        }

        try {
            exec('nvidia-smi --query-gpu=index,name,memory.total --format=csv,noheader 2>&1', $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                $gpuInfo = [];
                foreach ($output as $line) {
                    $parts = explode(', ', $line);
                    if (count($parts) >= 3) {
                        $gpuInfo[] = [
                            'index' => $parts[0],
                            'name' => $parts[1],
                            'memory' => $parts[2]
                        ];
                    }
                }
                
                error_log("LLM GPU Info: " . json_encode($gpuInfo));
                
                $cudaDevice = $this->llmConfig['cuda_device'];
                $gpuFound = false;
                foreach ($gpuInfo as $gpu) {
                    if ($gpu['index'] == $cudaDevice) {
                        $gpuFound = true;
                        error_log("LLM: Using GPU {$cudaDevice}: {$gpu['name']} ({$gpu['memory']})");
                        break;
                    }
                }
                
                if (!$gpuFound && $this->llmConfig['fallback_to_cpu']) {
                    error_log("LLM Warning: GPU {$cudaDevice} not found, will fallback to CPU if needed");
                }
            } else {
                error_log("LLM Warning: nvidia-smi not available, GPU detection failed");
            }
        } catch (\Exception $e) {
            error_log("LLM GPU Verification Error: " . $e->getMessage());
        }
    }

    public function getDecision(array $gameState, array $aiConfig, array $context): array
    {
        $startTime = microtime(true);
        
        try {
            if ($this->isCircuitBreakerOpen()) {
                error_log("LLM: Circuit breaker is OPEN, using fallback");
                return $this->getFallbackDecision($gameState, $aiConfig);
            }

            if (!$this->isAvailable()) {
                return $this->getFallbackDecision($gameState, $aiConfig);
            }
            
            $prompt = $this->constructPrompt($gameState, $aiConfig, $context);
            
            if ($this->llmConfig['enable_cache']) {
                $cachedResponse = $this->getCachedResponse($prompt);
                if ($cachedResponse !== null) {
                    $this->recordMetric('cache_hit');
                    $cachedResponse['llm_used'] = true;
                    $cachedResponse['cache_hit'] = true;
                    $cachedResponse['llm_execution_time'] = 0;
                    return $cachedResponse;
                }
            }
            
            $response = $this->callLLMWithRetry($prompt);
            
            if (empty($response)) {
                $this->recordCircuitBreakerFailure();
                return $this->getFallbackDecision($gameState, $aiConfig);
            }
            
            $decision = $this->parseResponse($response);
            
            if ($this->llmConfig['enable_cache']) {
                $this->cacheResponse($prompt, $decision);
            }
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $decision['llm_used'] = true;
            $decision['cache_hit'] = false;
            $decision['llm_execution_time'] = $executionTime;
            
            if ($this->llmConfig['enable_metrics']) {
                $this->recordMetric('success', $executionTime);
            }
            
            $this->resetCircuitBreaker();
            
            return $decision;
            
        } catch (\Exception $e) {
            error_log("LLM Integration Error: " . $e->getMessage());
            $this->recordCircuitBreakerFailure();
            if ($this->llmConfig['enable_metrics']) {
                $this->recordMetric('failure');
            }
            return $this->getFallbackDecision($gameState, $aiConfig);
        }
    }

    public function isAvailable(): bool
    {
        if ($this->lastAvailabilityCheck && (time() - $this->lastAvailabilityCheck) < 60) {
            return $this->isAvailableCache ?? false;
        }
        
        try {
            $ch = curl_init($this->llmConfig['endpoint']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            
            if (!empty($this->llmConfig['api_key'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->llmConfig['api_key']
                ]);
            }
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $this->isAvailableCache = ($httpCode > 0 && $httpCode < 500);
            $this->lastAvailabilityCheck = time();
            
            return $this->isAvailableCache;
            
        } catch (\Exception $e) {
            $this->isAvailableCache = false;
            $this->lastAvailabilityCheck = time();
            return false;
        }
    }

    private function callLLMWithRetry(string $prompt, int $attempt = 0): string
    {
        try {
            $response = $this->callLLM($prompt);
            
            if (!empty($response)) {
                return $response;
            }
            
            if ($attempt < $this->llmConfig['retry_attempts']) {
                $delay = $this->calculateExponentialBackoff($attempt);
                usleep($delay * 1000);
                return $this->callLLMWithRetry($prompt, $attempt + 1);
            }
            
            return '';
            
        } catch (\Exception $e) {
            if ($attempt < $this->llmConfig['retry_attempts']) {
                error_log("LLM Call Failed (attempt " . ($attempt + 1) . "): " . $e->getMessage());
                $delay = $this->calculateExponentialBackoff($attempt);
                usleep($delay * 1000);
                return $this->callLLMWithRetry($prompt, $attempt + 1);
            }
            
            error_log("LLM Call Failed after " . ($attempt + 1) . " attempts: " . $e->getMessage());
            return '';
        }
    }

    private function calculateExponentialBackoff(int $attempt): int
    {
        $baseDelay = $this->llmConfig['retry_delay'];
        $jitter = rand(0, 500);
        return (int)($baseDelay * pow(2, $attempt)) + $jitter;
    }

    private function constructPrompt(array $gameState, array $aiConfig, array $context): string
    {
        $personality = ucfirst($aiConfig['personality'] ?? 'Balanced');
        $difficulty = ucfirst($aiConfig['difficulty'] ?? 'Medium');
        
        $resources = $gameState['resources'] ?? [
            'wood' => 0,
            'clay' => 0,
            'iron' => 0,
            'crop' => 0
        ];
        
        $troops = $gameState['troops'] ?? [
            'total' => 0,
            'idle' => 0,
            'details' => []
        ];
        
        $threats = $gameState['threats'] ?? [
            'incoming_attacks' => 0,
            'nearby_enemies' => 0
        ];
        
        $prompt = "You are an AI player in Travian with the following profile:\n";
        $prompt .= "- Personality: {$personality}\n";
        $prompt .= "- Difficulty: {$difficulty}\n";
        $prompt .= "- Current Resources: Wood {$resources['wood']}, Clay {$resources['clay']}, Iron {$resources['iron']}, Crop {$resources['crop']}\n";
        $prompt .= "- Troops Available: {$troops['total']} total, {$troops['idle']} idle\n";
        
        if ($threats['incoming_attacks'] > 0) {
            $prompt .= "- Threats: {$threats['incoming_attacks']} incoming attacks\n";
        }
        
        $prompt .= "\nYour goal is to maximize your power and territory based on your {$personality} personality.\n\n";
        
        $prompt .= "Available actions:\n";
        $prompt .= "1. farm - Attack weak villages for resources\n";
        $prompt .= "2. build - Upgrade buildings\n";
        $prompt .= "3. train - Create more troops\n";
        $prompt .= "4. attack - Raid or conquer enemies\n";
        $prompt .= "5. defend - Evade or reinforce defenses\n";
        $prompt .= "6. trade - Trade resources\n";
        $prompt .= "7. idle - Wait and gather resources\n\n";
        
        $prompt .= "Response format (JSON):\n";
        $prompt .= "{\n";
        $prompt .= '  "action": "farm|build|train|attack|defend|trade|idle",' . "\n";
        $prompt .= '  "parameters": {},' . "\n";
        $prompt .= '  "reasoning": "Brief explanation"' . "\n";
        $prompt .= "}\n\n";
        
        $prompt .= "What do you decide to do?";
        
        return $prompt;
    }

    private function callLLM(string $prompt): string
    {
        try {
            $payload = $this->buildPayload($prompt);
            
            $ch = curl_init($this->llmConfig['endpoint']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $headers = ['Content-Type: application/json'];
            if (!empty($this->llmConfig['api_key'])) {
                $headers[] = 'Authorization: Bearer ' . $this->llmConfig['api_key'];
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->llmConfig['timeout']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if (!empty($error)) {
                throw new \Exception("cURL Error: " . $error);
            }
            
            if ($httpCode !== 200 || empty($response)) {
                throw new \Exception("HTTP Error: " . $httpCode);
            }
            
            $decoded = json_decode($response, true);
            
            return $this->extractResponse($decoded);
            
        } catch (\Exception $e) {
            error_log("LLM Call Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function buildPayload(string $prompt): array
    {
        $backend = $this->llmConfig['backend'];
        
        switch ($backend) {
            case 'ollama':
                return [
                    'model' => $this->llmConfig['model'],
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => $this->llmConfig['temperature'],
                        'num_predict' => $this->llmConfig['max_tokens'],
                        'num_gpu' => 1
                    ]
                ];
                
            case 'lm_studio':
            case 'vllm':
            case 'openai':
                return [
                    'model' => $this->llmConfig['model'],
                    'prompt' => $prompt,
                    'max_tokens' => $this->llmConfig['max_tokens'],
                    'temperature' => $this->llmConfig['temperature'],
                    'stream' => false
                ];
                
            case 'text_generation_webui':
                return [
                    'prompt' => $prompt,
                    'max_new_tokens' => $this->llmConfig['max_tokens'],
                    'temperature' => $this->llmConfig['temperature'],
                    'do_sample' => true,
                    'top_p' => 0.9,
                    'typical_p' => 1,
                    'repetition_penalty' => 1.1
                ];
                
            default:
                return [
                    'model' => $this->llmConfig['model'],
                    'prompt' => $prompt,
                    'max_tokens' => $this->llmConfig['max_tokens'],
                    'temperature' => $this->llmConfig['temperature']
                ];
        }
    }

    private function extractResponse(array $decoded): string
    {
        $backend = $this->llmConfig['backend'];
        
        switch ($backend) {
            case 'ollama':
                return $decoded['response'] ?? '';
                
            case 'lm_studio':
            case 'vllm':
            case 'openai':
                if (isset($decoded['choices'][0]['text'])) {
                    return $decoded['choices'][0]['text'];
                }
                return '';
                
            case 'text_generation_webui':
                if (isset($decoded['results'][0]['text'])) {
                    return $decoded['results'][0]['text'];
                }
                return '';
                
            default:
                return $decoded['response'] ?? $decoded['text'] ?? '';
        }
    }

    private function parseResponse(string $response): array
    {
        $response = trim($response);
        
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonStr = $matches[0];
            $decoded = json_decode($jsonStr, true);
            
            if ($decoded && isset($decoded['action'])) {
                return [
                    'action' => $decoded['action'],
                    'parameters' => $decoded['parameters'] ?? [],
                    'reasoning' => $decoded['reasoning'] ?? '',
                    'confidence' => 0.7,
                    'source' => 'llm'
                ];
            }
        }
        
        return [
            'action' => 'idle',
            'parameters' => [],
            'reasoning' => 'Failed to parse LLM response',
            'confidence' => 0.3,
            'source' => 'fallback'
        ];
    }

    private function getFallbackDecision(array $gameState, array $aiConfig): array
    {
        $resources = $gameState['resources'] ?? ['total' => 0];
        $troops = $gameState['troops'] ?? ['total' => 0];
        
        if ($troops['total'] > 50 && ($resources['total'] ?? 0) < 5000) {
            return [
                'action' => 'farm',
                'parameters' => ['intensity' => 'medium'],
                'reasoning' => 'Fallback: Need resources, have troops',
                'confidence' => 0.6,
                'source' => 'fallback'
            ];
        } elseif (($resources['total'] ?? 0) > 10000) {
            return [
                'action' => 'build',
                'parameters' => ['priority' => 'resource'],
                'reasoning' => 'Fallback: Have resources, should build',
                'confidence' => 0.6,
                'source' => 'fallback'
            ];
        } else {
            return [
                'action' => 'idle',
                'parameters' => [],
                'reasoning' => 'Fallback: Waiting for resources',
                'confidence' => 0.5,
                'source' => 'fallback'
            ];
        }
    }

    private function getCachedResponse(string $prompt): ?array
    {
        $cacheKey = md5($prompt);
        
        if (isset($this->responseCache[$cacheKey])) {
            $cached = $this->responseCache[$cacheKey];
            if (time() - $cached['timestamp'] < self::CACHE_TTL) {
                return $cached['response'];
            } else {
                unset($this->responseCache[$cacheKey]);
            }
        }
        
        return null;
    }

    private function cacheResponse(string $prompt, array $response): void
    {
        $cacheKey = md5($prompt);
        
        if (count($this->responseCache) >= self::MAX_CACHE_SIZE) {
            $oldestKey = array_key_first($this->responseCache);
            unset($this->responseCache[$oldestKey]);
        }
        
        $this->responseCache[$cacheKey] = [
            'response' => $response,
            'timestamp' => time()
        ];
    }

    private function isCircuitBreakerOpen(): bool
    {
        if ($this->circuitBreakerState === 'open') {
            if (time() - $this->circuitBreakerLastFailure >= self::CIRCUIT_BREAKER_TIMEOUT) {
                $this->circuitBreakerState = 'half_open';
                error_log("LLM Circuit Breaker: State changed to HALF_OPEN");
                return false;
            }
            return true;
        }
        
        return false;
    }

    private function recordCircuitBreakerFailure(): void
    {
        $this->circuitBreakerFailures++;
        $this->circuitBreakerLastFailure = time();
        
        if ($this->circuitBreakerFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            $this->circuitBreakerState = 'open';
            error_log("LLM Circuit Breaker: OPENED after {$this->circuitBreakerFailures} failures");
        }
    }

    private function resetCircuitBreaker(): void
    {
        if ($this->circuitBreakerState === 'half_open') {
            $this->circuitBreakerState = 'closed';
            $this->circuitBreakerFailures = 0;
            error_log("LLM Circuit Breaker: CLOSED - service recovered");
        } elseif ($this->circuitBreakerState === 'closed') {
            $this->circuitBreakerFailures = max(0, $this->circuitBreakerFailures - 1);
        }
    }

    private function recordMetric(string $type, int $executionTime = 0): void
    {
        if (!$this->llmConfig['enable_metrics']) {
            return;
        }
        
        $this->metrics['total_requests']++;
        
        switch ($type) {
            case 'success':
                $this->metrics['successful_requests']++;
                $this->metrics['total_response_time'] += $executionTime;
                break;
                
            case 'failure':
                $this->metrics['failed_requests']++;
                break;
                
            case 'cache_hit':
                $this->metrics['cache_hits']++;
                break;
        }
    }

    public function getMetrics(): array
    {
        $metrics = $this->metrics;
        
        if ($metrics['total_requests'] > 0) {
            $metrics['success_rate'] = round(
                ($metrics['successful_requests'] / $metrics['total_requests']) * 100,
                2
            );
            $metrics['cache_hit_rate'] = round(
                ($metrics['cache_hits'] / $metrics['total_requests']) * 100,
                2
            );
        } else {
            $metrics['success_rate'] = 0;
            $metrics['cache_hit_rate'] = 0;
        }
        
        if ($metrics['successful_requests'] > 0) {
            $metrics['avg_response_time'] = round(
                $metrics['total_response_time'] / $metrics['successful_requests'],
                2
            );
        } else {
            $metrics['avg_response_time'] = 0;
        }
        
        $metrics['circuit_breaker_state'] = $this->circuitBreakerState;
        $metrics['circuit_breaker_failures'] = $this->circuitBreakerFailures;
        
        return $metrics;
    }

    public function getGPUStatus(): array
    {
        $status = [
            'available' => false,
            'devices' => [],
            'cuda_device' => $this->llmConfig['cuda_device'],
            'memory_limit' => $this->llmConfig['gpu_memory_limit']
        ];
        
        try {
            exec('nvidia-smi --query-gpu=index,name,memory.used,memory.total,utilization.gpu,temperature.gpu --format=csv,noheader,nounits 2>&1', $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                $status['available'] = true;
                
                foreach ($output as $line) {
                    $parts = array_map('trim', explode(',', $line));
                    if (count($parts) >= 6) {
                        $status['devices'][] = [
                            'index' => $parts[0],
                            'name' => $parts[1],
                            'memory_used_mb' => (int)$parts[2],
                            'memory_total_mb' => (int)$parts[3],
                            'utilization_percent' => (int)$parts[4],
                            'temperature_celsius' => (int)$parts[5]
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("GPU Status Check Error: " . $e->getMessage());
        }
        
        return $status;
    }

    public function getConfiguration(): array
    {
        $config = $this->llmConfig;
        if (isset($config['api_key']) && !empty($config['api_key'])) {
            $config['api_key'] = '***REDACTED***';
        }
        return $config;
    }
}
