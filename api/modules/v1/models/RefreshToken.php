<?php
namespace app\modules\v1\models;
class RefreshToken extends \yii\redis\ActiveRecord
{
    public function save($runValidation = true, $attributeNames = null)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::save($runValidation, $attributeNames);
    }

    public function beforeSave($insert)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::beforeSave($insert);
    }

    public function beforeDelete()
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::beforeDelete();
    }

    public static function updateAll($attributes, $condition = null)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::updateAll($attributes, $condition);
    }

    public static function updateAllCounters($counters, $condition = null)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::updateAllCounters($counters, $condition);
    }

    public static function deleteAll($condition = null)
    {
        \app\components\IdentityAuthority::forbidLocalCredentials();
        return parent::deleteAll($condition);
    }
    /**
     * @return array 此记录的属性列表
     */
    public function attributes()
    {
        return ['id', 'user_id', 'key'];
    }

}
