<?php
namespace bosslib\yii\auth\authToken\traits;

use bosslib\yii\auth\authToken\components\IdentityInterface;
use bosslib\yii\auth\authToken\models\TokensStorageInterface;
use bosslib\yii\auth\authToken\models\UsersAuthTokens;
use Yii;
use yii\db\ActiveRecord;


/**
 * Trait UsersIdentityTrait
 * @package bosslib\yii\auth\authToken\traits
 * @property string|null $authKey
 * @property string|null $target
 * @property UsersAuthTokens $tokenModel
 */
trait UsersIdentityTrait
{
    private static $findIdentityTokenCache = [];

    /**
     * @var string|null
     */
    private $_authKey;

    /**
     * @var string|null
     */
    private $_loginMethod = null;

    /**
     * @var string|null
     */
    private $_target = null;

    /**
     * @var UsersAuthTokens
     */
    private $_tokenModel = null;

    /**
     * @param string $token
     * @return mixed
     */
    public static function findIdentity($token)
    {
        return static::findIdentityByAccessToken($token, null);
    }

    /**
     * @param string $token
     * @param null $target
     * @return mixed
     */
    public static function findIdentityByAccessToken($token, $target = null)
    {
        $cacheKey = $token.($target ? '_'.$target : '');

        if (empty($findIdentityTokenCache[$cacheKey])) {
            $findIdentityTokenCache[$cacheKey] = null;

            /** @var TokensStorageInterface $classStorage */
            $classStorage = Yii::$app->user->tokensStorageClass;

            /** @var IdentityInterface $identity */
            $identity = null;

            $tokenModel = $classStorage::getActiveToken($token, $target);

            if ($tokenModel) {
                $userId = $tokenModel->id_user;

                /** @var ActiveRecord $identityClass */
                $identityClass = Yii::$app->user->identityClass;

                $identity = $identityClass::findOne($userId);

                if ($identity) {
                    $identity->setAuthKey($tokenModel->token);
                    $identity->setTarget($tokenModel->target);
                    $identity->setTokenModel($tokenModel);

                    self::$findIdentityTokenCache[$cacheKey] = $identity;
                }
            }
        }

        return isset(self::$findIdentityTokenCache[$cacheKey]) ? self::$findIdentityTokenCache[$cacheKey] : null;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $authToken
     */
    public function setAuthKey($authToken)
    {
        $this->_authKey = $authToken;
    }

    /**
     * @return string
     */
    public function getAuthKey()
    {
        return $this->_authKey;
    }


    /**
     * @param $authToken
     * @return bool
     */
    public function validateAuthKey($authToken)
    {
        return $this->getAuthKey() === $authToken;
    }

    /**
     * @param string $target
     */
    public function setTarget($target)
    {
        $this->_target = $target;
    }

    /**
     * @return null|string
     */
    public function getTarget()
    {
        return $this->_target;
    }

    /**
     * @return null|string
     */
    public function getLoginMethod()
    {
        return $this->_loginMethod;
    }

    /**
     * Метод логина для записи в токен
     *
     * @param $method
     */
    public function setLoginMethod($method)
    {
        $this->_loginMethod = $method;
    }

    public function generateAuthKey()
    {
        $this->setAuthKey($this->addAuthToken($this->getTarget()));
        return $this->getAuthKey();
    }

    public function setTokenModel($model)
    {
        $this->_tokenModel = $model ? $model : null;
    }

    public function getTokenModel()
    {
        return $this->_tokenModel;
    }

    /**
     * @param $target
     * @return bool|string
     */
    public function addAuthToken($target)
    {
        /** @var TokensStorageInterface $classStorage */
        $classStorage = Yii::$app->user->tokensStorageClass;

        return $classStorage::createToken($this->id, [
            'target' => $target,
            'login_method' => $this->getLoginMethod()
        ]);
    }
}
