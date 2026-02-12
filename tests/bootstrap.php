<?php

/**
 * @file
 * Bootstrap for llm_content unit tests.
 */

declare(strict_types=1);

// Composer autoloader (vendor is at project root, same level as web/).
$autoloader = require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Register the module's PSR-4 namespace.
$autoloader->addPsr4('Drupal\\llm_content\\', dirname(__DIR__) . '/src');
$autoloader->addPsr4('Drupal\\Tests\\llm_content\\', dirname(__DIR__) . '/tests/src');

// Register core module namespaces needed by unit tests.
$webRoot = dirname(__DIR__, 4);
$autoloader->addPsr4('Drupal\\path_alias\\', $webRoot . '/core/modules/path_alias/src');
$autoloader->addPsr4('Drupal\\node\\', $webRoot . '/core/modules/node/src');
$autoloader->addPsr4('Drupal\\user\\', $webRoot . '/core/modules/user/src');
