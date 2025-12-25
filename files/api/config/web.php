<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'restful',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Shanghai',
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'modules' => [
        'v1' => [
            'class' => 'app\modules\v1\Module',
        ],
    ],

    'as cors' => [
        'class' => \yii\filters\Cors::className(),
        'cors' => [
            'Origin' => ['*'],
            'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
            'Access-Control-Request-Headers' => ['*'],
            'Access-Control-Allow-Credentials' => null,
            'Access-Control-Max-Age' => 86400,
            'Access-Control-Expose-Headers' => [
                'X-Pagination-Total-Count',
                'X-Pagination-Page-Count',
                'X-Pagination-Current-Page',
                'X-Pagination-Per-Page',
            ],
        ],
    ],
    'components' => [
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => getenv('REDIS_HOST'),
            'port' => getenv('REDIS_PORT'),
            'database' => getenv('REDIS_DB'),
        ],
        'jwt' => [
            'class' => \bizley\jwt\Jwt::class,
            'signer' => \bizley\jwt\Jwt::HS256,
            'signingKey' => [
                'key' => getenv('JWT_KEY'), // path to your PRIVATE key, you can start the path with @ to indicate this is a Yii alias
                'passphrase' => '', // omit it if you are not adding any passphrase
                'method' => \bizley\jwt\Jwt::METHOD_FILE,
            ],
            'validationConstraints' => static function (\bizley\jwt\Jwt $jwt) {
                $config = $jwt->getConfiguration();
                return [
                    new \Lcobucci\JWT\Validation\Constraint\SignedWith($config->signer(), $config->verificationKey()),
                    new \Lcobucci\JWT\Validation\Constraint\LooseValidAt(
                        new \Lcobucci\Clock\SystemClock(new \DateTimeZone(\Yii::$app->timeZone)),
                        new \DateInterval('PT10S')
                    ),
                ];
            }
        ],
        'request' => [
            'csrfParam' => '_csrf-api',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '1IGWolYN-GxNJpfxx84J24XhP2iFh4GZ',
        ],
        'cache' => [
            //  'class' => 'yii\caching\FileCache',
            'class' => 'yii\redis\Cache',
            // 全局默认缓存时长（秒）
            'defaultDuration' => 30,
            'redis' => [
                'hostname' => getenv('REDIS_HOST'),
                'port' => getenv('REDIS_PORT'),
                'database' => getenv('REDIS_DB'),
            ]
        ],
        'helper' => [
            'class' => 'app\components\Helper',
        ],
        'user' => [
            'identityClass' => 'app\modules\v1\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/server',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'HEAD test' => 'test',
                        'GET test' => 'test',
                        'GET public' => 'public',// 发布的场景
                        'GET checkin' => 'checkin',// 打卡使用的场景
                        'GET snapshot' => 'snapshot',// 快照 通过id
                        //还需要通过verse_id 得得到快照
                        'GET private' => 'private',//私有场景
                        //这里还要得到组的场景
                        'GET group' => 'group',
                        'GET tags' => 'tags',//得到所有标签
                    ],
                ],

                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/auth',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'POST login' => 'login',
                        'POST refresh' => 'refresh',
                    ],
                ],
               


              
                /* 给苹果用的


                                [
                                    'class' => 'yii\rest\UrlRule',
                                    'controller' => 'v1/auth',
                                    'pluralize' => false,
                                    'extraPatterns' => [
                                        'POST login' => 'login',
                                        'POST refresh' => 'refresh',
                                        'POST key-to-token' => 'key-to-token',
                                    ],
                                ],
                                [
                                    'pattern' => 'apple-app-site-association',
                                    'route' => 'site/apple-app-site-association',
                                    'suffix' => ''
                                ],*/
                /*
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/common',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET test' => 'test',
                        'POST verify' => 'verify',
                        'POST watermark' => 'watermark',
                        'POST report' => 'report',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/private',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET by-uuid' => 'by-uuid',
                        'GET by-verse-id' => 'by-verse-id',
                        'GET by-id' => 'by-id',
                        'GET list' => 'list',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/checkin',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET list' => 'list',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/public',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET by-uuid' => 'by-uuid',
                        'GET by-verse-id' => 'by-verse-id',
                        'GET by-id' => 'by-id',
                        'GET list' => 'list',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/auth',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'POST login' => 'login',
                        'POST refresh' => 'refresh',
                        'POST key-to-token' => 'key-to-token',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/tags',
                    'pluralize' => false,
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/phototype',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET info' => 'info',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/snapshot',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET public' => 'public',
                        'GET by-uuid' => 'by-uuid',
                        'GET by-verse-id' => 'by-verse-id',
                    ],
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/verse',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET open' => 'open',
                        'GET release' => 'release',
                        'GET public' => 'public',
                    ],
                ],*/

            ],
        ],

    ],
    'params' => $params,
];
/*

  public function actionDeviceRegister(){


  }
  public function actionGameReady(){

  }
  public function actionGameStart(){

  }
  public function actionGameOver(){


*/

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*', '::1'],
    ];
}

return $config;
