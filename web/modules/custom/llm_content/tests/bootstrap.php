<?php

/**
 * @file
 * Bootstrap for llm_content unit tests.
 */

// Composer autoloader (vendor is at project root, same level as web/).
$autoloader = require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Register the module's PSR-4 namespace.
$autoloader->addPsr4('Drupal\\llm_content\\', dirname(__DIR__) . '/src');
$autoloader->addPsr4('Drupal\\Tests\\llm_content\\', dirname(__DIR__) . '/tests/src');
