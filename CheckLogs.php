<?php

/**
 * CheckLogs PHP SDK
 * 
 * Official PHP SDK for CheckLogs.dev - A powerful log monitoring system
 * 
 * @package CheckLogs
 * @version 1.0.0
 * @author CheckLogs Team <support@checklogs.dev>
 * @license MIT
 * @link https://checklogs.dev
 */

namespace CheckLogs;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

// ========================================
// EXCEPTIONS
// ========================================

class CheckLogsException extends Exception {}

class ApiError extends CheckLogsException 
{
    protected $statusCode;
    protected $response;

    public function __construct(string $message, int $statusCode = 0, $response = null, Exception $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function getResponse() { return $this->response; }
    public function isAuthError(): bool { return in_array($this->statusCode, [401, 403]); }
    public function isRateLimitError(): bool { return $this->statusCode === 429; }
}

class NetworkError extends CheckLogsException 
{
    public function isTimeoutError(): bool { return strpos($this->getMessage(), 'timeout') !== false; }
}

class ValidationError extends CheckLogsException {}

// ========================================
// LOG LEVELS
// ========================================

abstract class LogLevel
{
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';

    private static $levels = [self::DEBUG, self::INFO, self::WARNING, self::ERROR, self::CRITICAL];

    public static function isValid(string $level): bool { return in_array($level, self::$levels); }
    public static function all(): array { return self::$levels; }
}

// ========================================
// CLIENT PRINCIPAL
// ========================================

class CheckLogsClient
{
    private $apiKey;
    private $httpClient;
    private $baseUrl;
    private $timeout;
    private $validatePayload;
    private $retryQueue = [];
    private $maxRetries = 3;
    private $retryDelay = 1000;

    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $options['baseUrl'] ?? 'https://checklogs.dev';
        $this->timeout = ($options['timeout'] ?? 5000) / 1000;
        $this->validatePayload = $options['validatePayload'] ?? true;

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'CheckLogs-PHP-SDK/1.0.0'
            ]
        ]);
    }

    public function log(array $data): ?array
    {
        if ($this->validatePayload) {
            $this->validateLogData($data);
        }
        
        $enrichedData = $this->enrichLogData($data);
        return $this->makeRequest('POST', '/api/logs', $enrichedData);
    }

    public function getLogs(array $filters = []): ?array
    {
        $query = http_build_query($filters);
        $endpoint = '/api/logs' . ($query ? '?' . $query : '');
        return $this->makeRequest('GET', $endpoint);
    }

    public function getStats(): ?array 
    { 
        return $this->makeRequest('GET', '/api/logs?stats=1'); 
    }

    // Méthodes de compatibilité - redirigent vers getStats()
    public function getSummary(): ?array { return $this->getStats(); }
    public function getErrorRate(): ?array { return $this->getStats(); }
    public function getTrend(): ?array { return $this->getStats(); }
    public function getPeakDay(): ?array { return $this->getStats(); }

    private function makeRequest(string $method, string $endpoint, array $data = null): ?array
    {
        $attempts = 0;
        
        while ($attempts <= $this->maxRetries) {
            try {
                $options = [];
                if ($data !== null) {
                    $options['json'] = $data;
                }

                $response = $this->httpClient->request($method, $endpoint, $options);
                $responseBody = $response->getBody()->getContents();
                
                // Vérifier si la réponse est vide
                if (empty($responseBody)) {
                    return null;
                }
                
                $decoded = json_decode($responseBody, true);
                
                // Vérifier les erreurs JSON
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new ApiError('Invalid JSON response: ' . json_last_error_msg());
                }
                
                return $decoded;

            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                
                // Essayer de décoder la réponse d'erreur JSON
                $errorData = json_decode($responseBody, true);
                $errorMessage = $errorData['error']['message'] ?? $e->getMessage();
                
                if ($statusCode >= 400 && $statusCode < 500) {
                    throw new ApiError('API Error: ' . $errorMessage, $statusCode, $errorData);
                }
                throw $e;

            } catch (ServerException $e) {
                $attempts++;
                if ($attempts > $this->maxRetries) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $errorData = json_decode($responseBody, true);
                    $errorMessage = $errorData['error']['message'] ?? $e->getMessage();
                    
                    throw new ApiError('Server Error: ' . $errorMessage, $statusCode, $errorData);
                }
                usleep($this->retryDelay * 1000 * $attempts);

            } catch (ConnectException $e) {
                $attempts++;
                if ($attempts > $this->maxRetries) {
                    throw new NetworkError('Network Error: ' . $e->getMessage());
                }
                usleep($this->retryDelay * 1000 * $attempts);

            } catch (RequestException $e) {
                throw new NetworkError('Request Error: ' . $e->getMessage());
            }
        }
        
        return null;
    }

    private function validateLogData(array $data): void
    {
        if (!isset($data['message']) || empty($data['message'])) {
            throw new ValidationError('Message is required');
        }

        if (strlen($data['message']) > 1024) {
            throw new ValidationError('Message must be max 1024 characters');
        }

        if (isset($data['level']) && !LogLevel::isValid($data['level'])) {
            throw new ValidationError('Invalid log level. Valid levels: ' . implode(', ', LogLevel::all()));
        }

        if (isset($data['source']) && strlen($data['source']) > 100) {
            throw new ValidationError('Source must be max 100 characters');
        }

        if (isset($data['user_id']) && !is_numeric($data['user_id'])) {
            throw new ValidationError('User ID must be a number');
        }

        if (isset($data['context']) && strlen(json_encode($data['context'])) > 5000) {
            throw new ValidationError('Context must be max 5000 characters when serialized');
        }
    }

    private function enrichLogData(array $data): array
    {
        $enriched = $data;
        
        // Définir le niveau par défaut
        if (!isset($enriched['level'])) {
            $enriched['level'] = LogLevel::INFO;
        }

        // Ajouter timestamp si pas présent
        if (!isset($enriched['timestamp'])) {
            $enriched['timestamp'] = date('c');
        }

        // Ajouter hostname si pas présent
        if (!isset($enriched['hostname'])) {
            $enriched['hostname'] = gethostname() ?: 'unknown';
        }

        // Ajouter informations processus si pas présent
        if (!isset($enriched['process'])) {
            $enriched['process'] = [
                'pid' => getmypid(),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
        }

        return $enriched;
    }

    public function getRetryQueueStatus(): array 
    { 
        return ['count' => count($this->retryQueue), 'items' => $this->retryQueue]; 
    }
    
    public function clearRetryQueue(): void 
    { 
        $this->retryQueue = []; 
    }

    public function flush(int $timeoutMs = 30000): bool
    {
        $startTime = microtime(true);
        $timeoutSeconds = $timeoutMs / 1000;

        while (!empty($this->retryQueue)) {
            if ((microtime(true) - $startTime) > $timeoutSeconds) {
                return false;
            }

            $logData = array_shift($this->retryQueue);
            try {
                $this->log($logData);
            } catch (Exception $e) {
                array_unshift($this->retryQueue, $logData);
                usleep(100000);
            }
        }

        return true;
    }
}

// ========================================
// LOGGER AVEC MÉTHODES DE COMMODITÉ
// ========================================

class CheckLogsLogger
{
    private $client;
    private $source;
    private $userId;
    private $defaultContext;
    private $silent;
    private $consoleOutput;
    private $enabledLevels;
    private $includeTimestamp;
    private $includeHostname;
    private $timers = [];

    public function __construct(string $apiKey, array $options = [])
    {
        $clientOptions = array_intersect_key($options, array_flip(['timeout', 'validatePayload', 'baseUrl']));
        $this->client = new CheckLogsClient($apiKey, $clientOptions);

        $this->source = $options['source'] ?? null;
        $this->userId = $options['user_id'] ?? null;
        $this->defaultContext = $options['defaultContext'] ?? [];
        $this->silent = $options['silent'] ?? false;
        $this->consoleOutput = $options['consoleOutput'] ?? false;
        $this->enabledLevels = $options['enabledLevels'] ?? LogLevel::all();
        $this->includeTimestamp = $options['includeTimestamp'] ?? true;
        $this->includeHostname = $options['includeHostname'] ?? true;
    }

    public function log(array $data): ?array
    {
        $level = $data['level'] ?? LogLevel::INFO;
        
        if (!in_array($level, $this->enabledLevels)) {
            return null;
        }

        $enrichedData = $this->enrichLogData($data);

        if (!$this->silent && $this->consoleOutput) {
            $this->outputToConsole($enrichedData);
        }

        if (!$this->silent) {
            try {
                return $this->client->log($enrichedData);
            } catch (Exception $e) {
                // En cas d'erreur, afficher l'erreur si consoleOutput est activé
                if ($this->consoleOutput) {
                    echo "[ERROR] Failed to send log to CheckLogs: " . $e->getMessage() . "\n";
                }
                // Ne pas lever l'exception pour ne pas casser l'application
                return null;
            }
        }

        return null;
    }

    public function debug(string $message, array $context = []): ?array
    {
        return $this->log(['message' => $message, 'level' => LogLevel::DEBUG, 'context' => $context]);
    }

    public function info(string $message, array $context = []): ?array
    {
        return $this->log(['message' => $message, 'level' => LogLevel::INFO, 'context' => $context]);
    }

    public function warning(string $message, array $context = []): ?array
    {
        return $this->log(['message' => $message, 'level' => LogLevel::WARNING, 'context' => $context]);
    }

    public function error(string $message, array $context = []): ?array
    {
        return $this->log(['message' => $message, 'level' => LogLevel::ERROR, 'context' => $context]);
    }

    public function critical(string $message, array $context = []): ?array
    {
        return $this->log(['message' => $message, 'level' => LogLevel::CRITICAL, 'context' => $context]);
    }

    public function child(array $childContext = []): CheckLogsLogger
    {
        $childOptions = [
            'source' => $this->source,
            'user_id' => $this->userId,
            'defaultContext' => array_merge($this->defaultContext, $childContext),
            'silent' => $this->silent,
            'consoleOutput' => $this->consoleOutput,
            'enabledLevels' => $this->enabledLevels,
            'includeTimestamp' => $this->includeTimestamp,
            'includeHostname' => $this->includeHostname
        ];

        // Récupérer l'API key du client parent
        $reflection = new \ReflectionClass($this->client);
        $apiKeyProperty = $reflection->getProperty('apiKey');
        $apiKeyProperty->setAccessible(true);
        $apiKey = $apiKeyProperty->getValue($this->client);

        return new CheckLogsLogger($apiKey, $childOptions);
    }

    public function time(string $id, string $message = null): callable
    {
        $startTime = microtime(true);
        $this->timers[$id] = $startTime;

        if ($message) {
            $this->info($message, ['timer_id' => $id, 'timer_start' => true]);
        }

        return function() use ($id, $startTime, $message) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            unset($this->timers[$id]);

            if ($message) {
                $this->info($message . ' - completed', [
                    'timer_id' => $id,
                    'duration_ms' => $duration,
                    'timer_end' => true
                ]);
            }

            return $duration;
        };
    }

    // Délégation vers le client
    public function getLogs(array $filters = []): ?array { return $this->client->getLogs($filters); }
    public function getStats(): ?array { return $this->client->getStats(); }
    public function getSummary(): ?array { return $this->client->getSummary(); }
    public function getErrorRate(): ?array { return $this->client->getErrorRate(); }
    public function getTrend(): ?array { return $this->client->getTrend(); }
    public function getPeakDay(): ?array { return $this->client->getPeakDay(); }
    public function getRetryQueueStatus(): array { return $this->client->getRetryQueueStatus(); }
    public function clearRetryQueue(): void { $this->client->clearRetryQueue(); }
    public function flush(int $timeoutMs = 30000): bool { return $this->client->flush($timeoutMs); }

    private function enrichLogData(array $data): array
    {
        $enriched = $data;

        if ($this->source) {
            $enriched['source'] = $this->source;
        }

        if ($this->userId) {
            $enriched['user_id'] = $this->userId;
        }

        $context = array_merge($this->defaultContext, $enriched['context'] ?? []);
        if (!empty($context)) {
            $enriched['context'] = $context;
        }

        return $enriched;
    }

    private function outputToConsole(array $data): void
    {
        $level = strtoupper($data['level'] ?? 'INFO');
        $message = $data['message'] ?? '';
        $timestamp = date('Y-m-d H:i:s');
        
        $output = "[$timestamp] [$level] $message";
        
        if (!empty($data['context'])) {
            $output .= ' ' . json_encode($data['context'], JSON_UNESCAPED_UNICODE);
        }

        echo $output . PHP_EOL;
    }
}