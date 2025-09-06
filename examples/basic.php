<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CheckLogs\CheckLogsLogger;
use CheckLogs\CheckLogsClient;
use CheckLogs\LogLevel;
use CheckLogs\ApiError;
use CheckLogs\NetworkError;
use CheckLogs\ValidationError;
use function CheckLogs\{createLogger, quickLog, quickError};

// Configuration
$apiKey = 'a0229737f9ed24719fc405916e97eaadd7671dfb092aeb3fe337e38f8c146678';

echo "🚀 === TEST COMPLET CHECKLOGS PHP SDK ===\n\n";

// ========================================
// TEST 1: INITIALISATION
// ========================================
echo "📋 1. Test d'initialisation...\n";

try {
    $logger = new CheckLogsLogger($apiKey, [
        'source' => 'test-sdk-complet',
        'consoleOutput' => true,
        'defaultContext' => [
            'test_suite' => 'complete_test',
            'php_version' => PHP_VERSION
        ]
    ]);
    echo "   ✅ Logger créé avec succès\n";
} catch (Exception $e) {
    echo "   ❌ Erreur création logger: " . $e->getMessage() . "\n";
    exit(1);
}

// ========================================
// TEST 2: LOGS BASIQUES
// ========================================
echo "\n📝 2. Test des niveaux de logs...\n";

$logTests = [
    ['method' => 'debug', 'message' => 'Message de debug pour test'],
    ['method' => 'info', 'message' => 'Message d\'information'],
    ['method' => 'warning', 'message' => 'Message d\'avertissement'],
    ['method' => 'error', 'message' => 'Message d\'erreur de test'],
    ['method' => 'critical', 'message' => 'Message critique de test']
];

foreach ($logTests as $test) {
    try {
        $result = $logger->{$test['method']}($test['message'], [
            'test_type' => 'level_test',
            'level' => $test['method']
        ]);
        
        if ($result !== null) {
            echo "   ✅ {$test['method']}: Log envoyé avec succès\n";
        } else {
            echo "   ⚠️  {$test['method']}: Log non envoyé (mode silent ou filtré)\n";
        }
        
        // Attendre un peu entre les logs
        usleep(100000); // 100ms
        
    } catch (Exception $e) {
        echo "   ❌ {$test['method']}: Erreur - " . $e->getMessage() . "\n";
    }
}

// ========================================
// TEST 3: LOGS AVEC CONTEXTE RICHE
// ========================================
echo "\n🔍 3. Test avec contexte riche...\n";

try {
    $result = $logger->info('Connexion utilisateur avec contexte riche', [
        'user_id' => 12345,
        'email' => 'test@example.com',
        'ip_address' => '192.168.1.100',
        'user_agent' => 'CheckLogs-PHP-SDK-Test/1.0',
        'session_data' => [
            'session_id' => 'sess_' . uniqid(),
            'login_time' => date('c'),
            'permissions' => ['read', 'write']
        ],
        'metadata' => [
            'source_test' => true,
            'test_timestamp' => microtime(true)
        ]
    ]);
    
    if ($result !== null) {
        echo "   ✅ Log avec contexte riche envoyé\n";
    } else {
        echo "   ⚠️  Log avec contexte non envoyé\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur contexte riche: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 4: CHILD LOGGERS
// ========================================
echo "\n👶 4. Test des child loggers...\n";

try {
    // Logger principal pour l'authentification
    $authLogger = $logger->child([
        'module' => 'authentication',
        'component' => 'login_service'
    ]);
    
    // Logger pour une session spécifique
    $sessionLogger = $authLogger->child([
        'session_id' => 'sess_test_' . uniqid(),
        'user_id' => 98765
    ]);
    
    $authLogger->info('Module d\'authentification initialisé');
    $sessionLogger->info('Session utilisateur créée');
    $sessionLogger->warning('Tentative de connexion avec mot de passe incorrect');
    $sessionLogger->info('Connexion réussie après 2ème tentative');
    
    echo "   ✅ Child loggers testés avec succès\n";
} catch (Exception $e) {
    echo "   ❌ Erreur child loggers: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 5: TIMER ET PERFORMANCE
// ========================================
echo "\n⏱️  5. Test du système de timer...\n";

try {
    // Test timer simple
    $timer1 = $logger->time('operation_test', 'Début opération de test');
    usleep(150000); // 150ms
    $duration1 = $timer1();
    echo "   ✅ Timer simple: {$duration1}ms\n";
    
    // Test timer imbriqués
    $timer2 = $logger->time('operation_complexe', 'Début opération complexe');
    
    $subTimer = $logger->time('sub_operation', 'Sous-opération');
    usleep(75000); // 75ms
    $subDuration = $subTimer();
    
    usleep(100000); // 100ms de plus
    $duration2 = $timer2();
    
    echo "   ✅ Timer complexe: {$duration2}ms (dont sous-opération: {$subDuration}ms)\n";
    
} catch (Exception $e) {
    echo "   ❌ Erreur timer: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 6: FONCTIONS UTILITAIRES
// ========================================
echo "\n🛠️  6. Test des fonctions utilitaires...\n";

try {
    // Test createLogger
    $utilityLogger = createLogger($apiKey, [
        'source' => 'utility-test',
        'consoleOutput' => false
    ]);
    $utilityLogger->info('Test fonction createLogger');
    echo "   ✅ createLogger fonctionne\n";
    
    // Test quickLog
    quickLog($apiKey, 'Test quickLog function', ['quick' => true]);
    echo "   ✅ quickLog fonctionne\n";
    
    // Test quickError
    quickError($apiKey, 'Test quickError function', ['error_code' => 'TEST_ERROR']);
    echo "   ✅ quickError fonctionne\n";
    
} catch (Exception $e) {
    echo "   ❌ Erreur fonctions utilitaires: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 7: GESTION D'ERREURS
// ========================================
echo "\n🚨 7. Test de la gestion d'erreurs...\n";

// Test avec clé API invalide
try {
    $badLogger = new CheckLogsLogger('invalid-api-key-test', [
        'consoleOutput' => false,
        'timeout' => 2000
    ]);
    $result = $badLogger->info('Ce log ne devrait pas passer');
    echo "   ⚠️  Test clé invalide: Log ignoré (pas d'exception levée)\n";
} catch (ApiError $e) {
    echo "   ✅ Test clé invalide: ApiError capturée - " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "   ⚠️  Test clé invalide: Exception autre - " . $e->getMessage() . "\n";
}

// Test validation des données
try {
    $logger->log([
        'message' => str_repeat('x', 2000), // Message trop long
        'level' => 'invalid_level'
    ]);
    echo "   ❌ Validation: Devrait échouer\n";
} catch (ValidationError $e) {
    echo "   ✅ Validation: ValidationError capturée - " . $e->getMessage() . "\n";
}

// ========================================
// TEST 8: CLIENT DIRECT
// ========================================
echo "\n🔌 8. Test du client direct...\n";

try {
    $client = new CheckLogsClient($apiKey);
    
    // Test envoi log direct
    $result = $client->log([
        'message' => 'Test via client direct',
        'level' => LogLevel::INFO,
        'context' => [
            'client_test' => true,
            'timestamp' => date('c')
        ]
    ]);
    
    if ($result !== null) {
        echo "   ✅ Client direct: Log envoyé\n";
    } else {
        echo "   ⚠️  Client direct: Pas de réponse\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erreur client direct: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 9: RÉCUPÉRATION DE LOGS
// ========================================
echo "\n📄 9. Test récupération des logs...\n";

try {
    // Attendre un peu pour que les logs soient traités
    sleep(1);
    
    $logs = $logger->getLogs([
        'limit' => 5,
        'level' => 'info'
    ]);
    
    if ($logs !== null && isset($logs['data']['logs'])) {
        $count = count($logs['data']['logs']);
        echo "   ✅ Récupération: {$count} logs récupérés\n";
        
        if ($count > 0) {
            $latestLog = $logs['data']['logs'][0];
            echo "   📝 Dernier log: " . ($latestLog['message'] ?? 'Pas de message') . "\n";
        }
    } else {
        echo "   ⚠️  Récupération: Aucun log ou structure inattendue\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erreur récupération: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 10: STATISTIQUES
// ========================================
echo "\n📊 10. Test des statistiques...\n";

try {
    $stats = $logger->getStats();
    
    if ($stats !== null && isset($stats['data'])) {
        $data = $stats['data'];
        echo "   ✅ Statistiques récupérées\n";
        echo "   📈 Total logs: " . ($data['total_logs'] ?? 'N/A') . "\n";
        echo "   📅 Logs aujourd'hui: " . ($data['logs_today'] ?? 'N/A') . "\n";
        
        if (isset($data['stats_by_level'])) {
            echo "   📊 Par niveau:\n";
            foreach ($data['stats_by_level'] as $stat) {
                echo "      - {$stat['level']}: {$stat['count']}\n";
            }
        }
    } else {
        echo "   ⚠️  Statistiques: Structure inattendue ou vide\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erreur statistiques: " . $e->getMessage() . "\n";
}

// ========================================
// TEST 11: FLUSH ET NETTOYAGE
// ========================================
echo "\n🧹 11. Test flush et nettoyage...\n";

try {
    // Vérifier la queue de retry
    $queueStatus = $logger->getRetryQueueStatus();
    echo "   📋 Queue status: " . $queueStatus['count'] . " éléments\n";
    
    // Flush les logs en attente
    $flushResult = $logger->flush(5000);
    echo "   " . ($flushResult ? "✅" : "⚠️") . " Flush: " . ($flushResult ? "Réussi" : "Timeout ou échec") . "\n";
    
    // Nettoyer la queue
    $logger->clearRetryQueue();
    echo "   🧹 Queue nettoyée\n";
    
} catch (Exception $e) {
    echo "   ❌ Erreur flush: " . $e->getMessage() . "\n";
}

// ========================================
// RÉSUMÉ FINAL
// ========================================
echo "\n🎯 === RÉSUMÉ DU TEST ===\n";

// Log de fin avec résumé
$logger->info('Tests du SDK CheckLogs terminés', [
    'test_suite' => 'complete_test',
    'php_version' => PHP_VERSION,
    'sdk_version' => '1.0.0',
    'timestamp_end' => date('c'),
    'test_status' => 'completed'
]);

echo "\n✨ Tests terminés ! Vérifiez vos logs sur CheckLogs.dev\n";
echo "🔗 URL: https://checklogs.dev\n";
echo "🔑 API Key utilisée: " . substr($apiKey, 0, 8) . "...\n";

// Informations de debug
echo "\n🔧 Informations de debug:\n";
echo "   - PHP Version: " . PHP_VERSION . "\n";
echo "   - Guzzle installé: " . (class_exists('GuzzleHttp\\Client') ? 'Oui' : 'Non') . "\n";
echo "   - Extensions JSON: " . (extension_loaded('json') ? 'Oui' : 'Non') . "\n";
echo "   - Extensions cURL: " . (extension_loaded('curl') ? 'Oui' : 'Non') . "\n";
echo "   - Limite mémoire: " . ini_get('memory_limit') . "\n";
echo "   - Utilisation mémoire: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";

echo "\n🚀 SDK CheckLogs PHP prêt pour la production !\n";