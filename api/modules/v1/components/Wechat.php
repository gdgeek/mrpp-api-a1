<?php
namespace app\modules\v1\components;
use EasyWeChat\OfficialAccount\Application;
use yii\base\Component;

class Wechat extends Component
{

    public $app_id;
    public $secret;
    public $token;
    public $aes_key;
    
    public function application()
    {
        $config = [
            'app_id' => $this->app_id,
            'secret' =>  $this->secret,
            'token' => $this->token,
            'aes_key' =>  $this->aes_key,// 明文模式请勿填写 EncodingAESKey
            //...
        ];
        return new Application($config);
    }
    
}

