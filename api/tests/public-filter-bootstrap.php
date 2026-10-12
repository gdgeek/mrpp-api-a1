<?php

defined('YII_ENV') or define('YII_ENV', 'test');
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', false);

// An external Yii2/PHPUnit vendor directory lets this isolated suite run without
// loading application config, credentials, or the Codeception business database.
$vendor = getenv('A1_TEST_VENDOR') ?: dirname(__DIR__) . '/vendor';
require $vendor . '/autoload.php';
require_once $vendor . '/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', dirname(__DIR__));
