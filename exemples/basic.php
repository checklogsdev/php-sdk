<?php

require_once '../vendor/autoload.php';

use CheckLogs\CheckLogsLogger;
use function CheckLogs\{createLogger, quickLog};

// Remplacez par votre vraie clé API pour tester
$apiKey = '24e3499f784b941ae020852c89e4153b754dffb49242fd62266b10a9899c3bf8';

echo "=== Test du SDK CheckLogs PHP ===\n";

// Test 1: Logger basique
echo "1. Test logger basique...\n";
$logger = new CheckLogsLogger($apiKey);
$logger->info('Test SDK - Logger basique');

// Test 2: Fonction utilitaire
echo "2. Test fonction createLogger...\n";
$utilityLogger = createLogger($apiKey, [
    'source' => 'example-test',
    'consoleOutput' => true
]);
$utilityLogger->info('Test SDK - Fonction utilitaire');

// Test 3: Log rapide
echo "3. Test quickLog...\n";
quickLog($apiKey, 'Test SDK - Quick log');

// Test 4: Child logger
echo "4. Test child logger...\n";
$childLogger = $logger->child(['module' => 'test']);
$childLogger->info('Test SDK - Child logger');

// Test 5: Timer
echo "5. Test timer...\n";
$timer = $logger->time('test-timer', 'Début timer de test');
usleep(100000); // 100ms
$duration = $timer();
echo "Timer terminé: {$duration}ms\n";

echo "=== Tests terminés ===\n";