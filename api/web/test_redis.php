<?php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

$app = new yii\web\Application($config);

echo "Attempting to get redis component...\n";
try {
    $redis = Yii::$app->get('redis');
    echo "Redis component class: " . get_class($redis) . "\n";
    echo "Implements ConnectionInterface: " . ($redis instanceof \yii\redis\ConnectionInterface ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Error getting redis component: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nAttempting to get cache component...\n";
try {
    $cache = Yii::$app->get('cache');
    echo "Cache component class: " . get_class($cache) . "\n";
} catch (\Exception $e) {
    echo "Error getting cache component: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
