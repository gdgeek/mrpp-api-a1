<?php

namespace app\models;

use Yii;
use yii\base\Model;
use app\components\IdentityAuthority;
use app\components\IdentityWebUser;

/**
 * LoginForm is the model behind the login form.
 *
 * @property-read User|null $user
 *
 */
class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;

    private $_user = false;
    private ?array $identityResult = null;


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (IdentityAuthority::enabled()) {
            if (!$this->hasErrors()) {
                try {
                    $this->identityResult = IdentityAuthority::run(fn() => IdentityAuthority::provider()->login(
                        (string)$this->username, (string)$this->password, IdentityAuthority::requestContext()));
                } catch (\yii\web\UnauthorizedHttpException $exception) {
                    $this->addError($attribute, 'Incorrect username or password.');
                }
            }
            return;
        }
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if (IdentityAuthority::enabled()) {
            if (!$this->validate()) return false;
            if (!Yii::$app->user instanceof IdentityWebUser || $this->identityResult === null) {
                throw new \yii\web\HttpException(503, 'Identity UI session is unavailable.');
            }
            return Yii::$app->user->loginWithIdentityTokens($this->identityResult['authTokens'], $this->rememberMe ? 3600 * 24 * 30 : 0);
        }
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600*24*30 : 0);
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if (IdentityAuthority::enabled()) {
            return $this->identityResult === null ? null : \app\modules\v1\models\User::fromIdentitySubject($this->identityResult['subject']);
        }
        if ($this->_user === false) {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }
}
