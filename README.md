# CheckLogs PHP SDK

[![Latest Version](https://img.shields.io/packagist/v/checklogs/php-sdk.svg?style=flat-square)](https://packagist.org/packages/checklogs/php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/checklogs/php-sdk.svg?style=flat-square)](https://packagist.org/packages/checklogs/php-sdk)
[![License](https://img.shields.io/packagist/l/checklogs/php-sdk.svg?style=flat-square)](https://packagist.org/packages/checklogs/php-sdk)

SDK PHP officiel pour CheckLogs.dev - Un système puissant de surveillance des logs.

## Installation

```bash
composer require checklogs/php-sdk
```

## Utilisation Rapide

```php
<?php

require_once 'vendor/autoload.php';

use CheckLogs\CheckLogsLogger;
use CheckLogs\LogLevel;

// Créer un logger
$logger = new CheckLogsLogger('votre-cle-api');

// Logger des messages
$logger->info('Application démarrée');
$logger->error('Erreur de connexion', ['code' => 500]);
```

## Fonctions Utilitaires

```php
<?php

use function CheckLogs\{createLogger, quickLog, quickError};

// Fonction rapide pour créer un logger
$logger = createLogger('votre-cle-api');

// Logs rapides
quickLog('votre-cle-api', 'Message rapide');
quickError('votre-cle-api', 'Erreur rapide');
```

## Fonctionnalités

✅ **Simple et rapide** - Intégration en 2 minutes  
✅ **Mécanisme de retry** automatique  
✅ **Child loggers** avec contexte hérité  
✅ **Mesure de performance** intégrée  
✅ **Gestion d'erreurs** complète  
✅ **Fonctions utilitaires** pratiques  
✅ **Compatible PHP 7.4+** et 8.x  

## Configuration

```php
$logger = new CheckLogsLogger('votre-cle-api', [
    'source' => 'mon-app',                    // Source par défaut
    'defaultContext' => ['env' => 'prod'],    // Contexte par défaut
    'consoleOutput' => true,                  // Afficher aussi en console
    'timeout' => 5000,                        // Timeout en ms
]);
```

## Niveaux de Log

```php
$logger->debug('Message de debug');
$logger->info('Information générale');
$logger->warning('Attention');
$logger->error('Erreur');
$logger->critical('Erreur critique');
```

## Child Loggers

```php
$mainLogger = createLogger('api-key', [
    'defaultContext' => ['service' => 'api']
]);

$userLogger = $mainLogger->child(['module' => 'user']);
$orderLogger = $mainLogger->child(['module' => 'order']);

// Chaque child hérite du contexte parent
$userLogger->info('Utilisateur créé');  // Context: service=api, module=user
$orderLogger->error('Commande échouée'); // Context: service=api, module=order
```

## Mesure de Performance

```php
// Mesurer le temps d'exécution
$endTimer = $logger->time('db-query', 'Requête base de données');

// ... votre code ...

$duration = $endTimer(); // Log automatique avec la durée
echo "Opération: {$duration}ms";
```

## Fonctions Utilitaires Avancées

```php
use function CheckLogs\{
    measureExecutionTime,
    createRequestLogger,
    setupGlobalErrorHandling,
    formatMessage
};

// Mesurer une fonction
$result = measureExecutionTime(function() {
    // Votre code
    return "résultat";
}, $logger, 'mon-operation');

// Logger pour requêtes HTTP
$requestLogger = createRequestLogger('api-key', 'POST', '/api/users');

// Gestion globale des erreurs
setupGlobalErrorHandling($logger);

// Template de messages
$message = formatMessage('Utilisateur {user_id} a effectué {action}', [
    'user_id' => 123,
    'action' => 'connexion'
]);
```

## Gestion d'Erreurs

```php
use CheckLogs\{ApiError, NetworkError, ValidationError};

try {
    $logger->info('Test');
} catch (ValidationError $e) {
    echo "Erreur de validation: " . $e->getMessage();
} catch (ApiError $e) {
    echo "Erreur API: " . $e->getStatusCode();
    
    if ($e->isAuthError()) {
        echo "Problème d'authentification";
    }
} catch (NetworkError $e) {
    echo "Erreur réseau: " . $e->getMessage();
}
```

## Statistiques

```php
// Obtenir des statistiques
$stats = $logger->getStats();
$errorRate = $logger->getErrorRate();
$summary = $logger->getSummary();

echo "Total logs: " . $stats['data']['total_logs'];
echo "Taux d'erreur: " . $errorRate['data']['rate'] . "%";
```

## Exemples d'Intégration

### Laravel

```php
// Dans un Service Provider
use CheckLogs\CheckLogsLogger;

$this->app->singleton(CheckLogsLogger::class, function ($app) {
    return new CheckLogsLogger(config('services.checklogs.api_key'), [
        'source' => config('app.name'),
        'defaultContext' => ['env' => config('app.env')]
    ]);
});

// Dans un Controller
public function store(Request $request, CheckLogsLogger $logger)
{
    $logger->info('Création utilisateur', $request->only(['email']));
    // ...
}
```

### Symfony

```php
// Dans services.yaml
services:
    CheckLogs\CheckLogsLogger:
        arguments:
            $apiKey: '%env(CHECKLOGS_API_KEY)%'
            $options:
                source: '%kernel.project_dir%'

// Dans un Controller
public function create(CheckLogsLogger $logger)
{
    $logger->info('Action create appelée');
    // ...
}
```

### Application Vanilla PHP

```php
<?php
require_once 'vendor/autoload.php';

use CheckLogs\CheckLogsLogger;
use function CheckLogs\{setupGlobalErrorHandling, createRequestLogger};

// Logger principal
$logger = new CheckLogsLogger('votre-cle-api', [
    'source' => 'mon-site',
    'consoleOutput' => true
]);

// Configuration globale des erreurs
setupGlobalErrorHandling($logger);

// Logger pour chaque requête
$requestLogger = createRequestLogger(
    'votre-cle-api',
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI'],
    ['user_ip' => $_SERVER['REMOTE_ADDR']]
);

$requestLogger->info('Requête reçue');

try {
    // Votre logique applicative
    processRequest();
    $requestLogger->info('Requête traitée avec succès');
    
} catch (Exception $e) {
    $requestLogger->error('Erreur lors du traitement', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
```

## Configuration Avancée

### Variables d'Environnement

```bash
# .env
CHECKLOGS_API_KEY=votre-cle-api
CHECKLOGS_BASE_URL=https://api.checklogs.dev
CHECKLOGS_TIMEOUT=5000
```

```php
$logger = new CheckLogsLogger($_ENV['CHECKLOGS_API_KEY'], [
    'baseUrl' => $_ENV['CHECKLOGS_BASE_URL'] ?? 'https://api.checklogs.dev',
    'timeout' => (int)($_ENV['CHECKLOGS_TIMEOUT'] ?? 5000)
]);
```

### Logger Global

```php
use function CheckLogs\{configureGlobalLogger, getGlobalLogger, globalLog};

// Configurer une seule fois
configureGlobalLogger('votre-cle-api', [
    'source' => 'global-app',
    'consoleOutput' => true
]);

// Utiliser partout dans l'application
$logger = getGlobalLogger();
$logger->info('Message depuis le logger global');

// Ou directement
globalLog('info', 'Message direct');
```

## API Reference

### CheckLogsLogger

| Méthode | Description |
|---------|-------------|
| `__construct($apiKey, $options)` | Créer un logger |
| `info($message, $context)` | Log niveau info |
| `error($message, $context)` | Log niveau error |
| `warning($message, $context)` | Log niveau warning |
| `debug($message, $context)` | Log niveau debug |
| `critical($message, $context)` | Log niveau critical |
| `child($context)` | Créer un child logger |
| `time($id, $message)` | Démarrer un timer |
| `getStats()` | Obtenir les statistiques |
| `flush($timeout)` | Envoyer tous les logs en attente |

### Fonctions Utilitaires

| Fonction | Description |
|----------|-------------|
| `createLogger($apiKey, $options)` | Créer un logger |
| `createClient($apiKey, $options)` | Créer un client |
| `quickLog($apiKey, $message, $context)` | Log rapide info |
| `quickError($apiKey, $message, $context)` | Log rapide error |
| `measureExecutionTime($callback, $logger, $name)` | Mesurer une fonction |
| `setupGlobalErrorHandling($logger)` | Gérer les erreurs globales |
| `formatMessage($template, $data)` | Formater avec template |

## Requirements

- **PHP 7.4+** ou **PHP 8.x**
- **ext-json** (généralement inclus)
- **GuzzleHTTP 7.0+** (installé automatiquement)

## License

MIT License - voir le fichier [LICENSE](LICENSE) pour plus de détails.

## Support

- **Documentation** : [https://docs.checklogs.dev](https://docs.checklogs.dev)
- **Issues** : [GitHub Issues](https://github.com/checklogsdev/php-sdk/issues)
- **Email** : [contact@loggersimple.com](mailto:contact@loggersimple.com)

---

## Quick Start Guide

1. **Installer** : `composer require checklogs/php-sdk`
2. **Utiliser** :
   ```php
   use CheckLogs\CheckLogsLogger;
   $logger = new CheckLogsLogger('votre-cle-api');
   $logger->info('Hello CheckLogs!');
   ```
3. **Configurer** : Ajouter vos options selon vos besoins
4. **Déployer** : Vos logs apparaissent automatiquement sur CheckLogs.dev