<?php
namespace app\modules\v1\helper;

use Yii;
use yii\filters\auth\AuthMethod;
use yii\web\UnauthorizedHttpException;
use app\modules\v1\models\Device;
class DeviceFingerprintAuth extends AuthMethod
{
    
    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
      return true;
     
      $data = \Yii::$app->request->get();
      if(isset($data['uuid']) && isset($data['timestamp']) && isset($data['fingerprint'])){
      
        $uuid =  urldecode($data['uuid']);
        $timestamp =  urldecode($data['timestamp']);
        $fingerprint = urldecode($data['fingerprint']);
        $inputString = $uuid."7h35cfb96kPMQAFKWE3X6X8H6BChpnHN". $timestamp;
        if($fingerprint == md5($inputString)){
          return true;
        }
      }
     
      return null;
    }


}