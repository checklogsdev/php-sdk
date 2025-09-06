<?php

/**
 * CheckLogs PHP SDK - Fonctions utilitaires
 * 
 * @package CheckLogs
 * @version 1.0.0
 */

namespace CheckLogs;

/**
 * Créer une instance de logger
 * 
 * @param string $apiKey Clé API CheckLogs
 * @param array $options Options de configuration
 * @return CheckLogsLogger
 */
function createLogger(string $apiKey, array $options = []): CheckLogsLogger
{
    return new CheckLogsLogger($apiKey, $options);
}

/**
 * Créer une instance de client
 * 
 * @param string $apiKey Clé API CheckLogs
 * @param array $options Options de configuration
 * @return CheckLogsClient
 */
function createClient(string $apiKey, array $options = []): CheckLogsClient
{
    return new CheckLogsClient($apiKey, $options);
}

/**
 * Logger rapide - Log info
 * 
 * @param string $apiKey Clé API CheckLogs
 * @param string $message Message à logger
 * @param array $context Contexte additionnel
 * @return array|null Réponse de l'API
 */
function quickLog(string $apiKey, string $message, array $context = []): ?array
{
    static $logger = null;
    
    if ($logger === null) {
        $logger = new CheckLogsLogger($apiKey);
    }
    
    return $logger->info($message, $context);
}

/**
 * Logger rapide - Log error
 * 
 * @param string $apiKey Clé API CheckLogs
 * @param string $message Message d'erreur
 * @param array $context Contexte additionnel
 * @return array|null Réponse de l'API
 */
function quickError(string $apiKey, string $message, array $context = []): ?array
{
    static $logger = null;
    
    if ($logger === null) {
        $logger = new CheckLogsLogger($apiKey);
    }
    
    return $logger->error($message, $context);
}

/**
 * Configurer un logger global
 * 
 * @param string $apiKey Clé API CheckLogs
 * @param array $options Options de configuration
 * @return CheckLogsLogger
 */
function configureGlobalLogger(string $apiKey, array $options = []): CheckLogsLogger
{
    global $_checkLogsGlobalLogger;
    
    $_checkLogsGlobalLogger = new CheckLogsLogger($apiKey, $options);
    
    return $_checkLogsGlobalLogger;
}

/**
 * Récupérer le logger global
 * 
 * @return CheckLogsLogger|null
 */
function getGlobalLogger(): ?CheckLogsLogger
{
    global $_checkLogsGlobalLogger;
    
    return $_checkLogsGlobalLogger ?? null;
}

/**
 * Log avec le logger global
 * 
 * @param string $level Niveau de log
 * @param string $message Message
 * @param array $context Contexte
 * @return array|null
 */
function globalLog(string $level, string $message, array $context = []): ?array
{
    $logger = getGlobalLogger();
    
    if ($logger === null) {
        throw new CheckLogsException('Global logger not configured. Call configureGlobalLogger() first.');
    }
    
    return $logger->log([
        'level' => $level,
        'message' => $message,
        'context' => $context
    ]);
}

/**
 * Valider une clé API (format basique)
 * 
 * @param string $apiKey Clé API à valider
 * @return bool
 */
function validateApiKey(string $apiKey): bool
{
    // Validation basique : non vide et longueur minimale
    return !empty($apiKey) && strlen($apiKey) >= 10;
}

/**
 * Formater un message de log avec template
 * 
 * @param string $template Template du message (ex: "User {user_id} performed {action}")
 * @param array $data Données à injecter
 * @return string
 */
function formatMessage(string $template, array $data): string
{
    $message = $template;
    
    foreach ($data as $key => $value) {
        $placeholder = '{' . $key . '}';
        $message = str_replace($placeholder, (string)$value, $message);
    }
    
    return $message;
}

/**
 * Créer un contexte standardisé pour les erreurs
 * 
 * @param Exception $exception Exception capturée
 * @param array $additionalContext Contexte additionnel
 * @return array
 */
function createErrorContext(\Exception $exception, array $additionalContext = []): array
{
    return array_merge([
        'exception_class' => get_class($exception),
        'exception_message' => $exception->getMessage(),
        'exception_code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ], $additionalContext);
}

/**
 * Mesurer le temps d'exécution d'une fonction
 * 
 * @param callable $callback Fonction à mesurer
 * @param CheckLogsLogger|null $logger Logger pour enregistrer le temps
 * @param string|null $operationName Nom de l'opération
 * @return mixed Résultat de la fonction
 */
function measureExecutionTime(callable $callback, ?CheckLogsLogger $logger = null, ?string $operationName = null)
{
    $startTime = microtime(true);
    $operationName = $operationName ?? 'operation';
    
    if ($logger) {
        $logger->info("Starting {$operationName}");
    }
    
    try {
        $result = $callback();
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($logger) {
            $logger->info("Completed {$operationName}", [
                'execution_time_ms' => $executionTime,
                'status' => 'success'
            ]);
        }
        
        return $result;
        
    } catch (\Exception $e) {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($logger) {
            $logger->error("Failed {$operationName}", [
                'execution_time_ms' => $executionTime,
                'status' => 'error',
                'error' => $e->getMessage()
            ]);
        }
        
        throw $e;
    }
}

/**
 * Créer un logger pour une requête HTTP
 * 
 * @param string $apiKey Clé API
 * @param string $method Méthode HTTP
 * @param string $uri URI de la requête
 * @param array $additionalContext Contexte additionnel
 * @return CheckLogsLogger
 */
function createRequestLogger(string $apiKey, string $method, string $uri, array $additionalContext = []): CheckLogsLogger
{
    $requestId = uniqid('req_');
    
    $context = array_merge([
        'request_id' => $requestId,
        'method' => strtoupper($method),
        'uri' => $uri,
        'timestamp' => date('c')
    ], $additionalContext);
    
    return createLogger($apiKey, [
        'source' => 'http_request',
        'defaultContext' => $context
    ]);
}

/**
 * Logger pour les erreurs PHP
 * 
 * @param CheckLogsLogger $logger Logger à utiliser
 * @return callable Handler d'erreur
 */
function createPhpErrorHandler(CheckLogsLogger $logger): callable
{
    return function($severity, $message, $file, $line) use ($logger) {
        $levelMap = [
            E_ERROR => LogLevel::CRITICAL,
            E_WARNING => LogLevel::WARNING,
            E_NOTICE => LogLevel::INFO,
            E_USER_ERROR => LogLevel::ERROR,
            E_USER_WARNING => LogLevel::WARNING,
            E_USER_NOTICE => LogLevel::INFO,
            E_STRICT => LogLevel::INFO,
            E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_DEPRECATED => LogLevel::WARNING,
            E_USER_DEPRECATED => LogLevel::WARNING
        ];
        
        $level = $levelMap[$severity] ?? LogLevel::ERROR;
        
        $logger->log([
            'level' => $level,
            'message' => "PHP Error: {$message}",
            'context' => [
                'severity' => $severity,
                'file' => $file,
                'line' => $line,
                'error_type' => 'php_error'
            ]
        ]);
        
        return false; // Continue normal error handling
    };
}

/**
 * Logger pour les exceptions non capturées
 * 
 * @param CheckLogsLogger $logger Logger à utiliser
 * @return callable Handler d'exception
 */
function createExceptionHandler(CheckLogsLogger $logger): callable
{
    return function($exception) use ($logger) {
        $logger->critical('Uncaught exception', createErrorContext($exception, [
            'error_type' => 'uncaught_exception'
        ]));
        
        // Flush logs before termination
        $logger->flush(5000);
    };
}

/**
 * Configurer la gestion globale des erreurs
 * 
 * @param CheckLogsLogger $logger Logger à utiliser
 * @return void
 */
function setupGlobalErrorHandling(CheckLogsLogger $logger): void
{
    // Handler d'erreurs PHP
    set_error_handler(createPhpErrorHandler($logger));
    
    // Handler d'exceptions
    set_exception_handler(createExceptionHandler($logger));
    
    // Handler de shutdown pour les erreurs fatales
    register_shutdown_function(function() use ($logger) {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $logger->critical('Fatal error detected', [
                'error_type' => 'fatal_error',
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
        
        // Ensure all logs are sent before shutdown
        $logger->flush(5000);
    });
}

/**
 * Créer un contexte de performance système
 * 
 * @return array
 */
function getSystemPerformanceContext(): array
{
    return [
        'memory_usage' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true),
        'memory_limit' => ini_get('memory_limit'),
        'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        'php_version' => PHP_VERSION,
        'server_time' => date('c')
    ];
}

/**
 * Débugger - Afficher les informations du SDK
 * 
 * @return array
 */
function getSDKInfo(): array
{
    return [
        'sdk_version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'guzzle_version' => class_exists('\\GuzzleHttp\\Client') ? 'installed' : 'not_installed',
        'extensions' => [
            'json' => extension_loaded('json'),
            'curl' => extension_loaded('curl'),
            'openssl' => extension_loaded('openssl')
        ],
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
}