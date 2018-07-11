<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 18.05.2018
 * Time: 13:22
 */

namespace bosslib\yii\auth\authToken\components;

use bosslib\yii\auth\authToken\models\TokensStorageInterface;
use Yii;
use yii\base\InvalidValueException;

/**
 * Class User
 * @property IdentityInterface $identity
 * @package bosslib\yii\auth\authToken\components
 */
class User extends \yii\web\User
{
    const LOGGED_COOKIE_LIFE_TIME = 2592000;
    const SESSION_AUTH_TOKEN_NAME = '__auth_token_web';

    public $idParam = self::SESSION_AUTH_TOKEN_NAME;

    public $tokensStorageClass = 'bosslib\yii\auth\authToken\models\UsersAuthTokens';

    /**
     * @param \yii\web\IdentityInterface $identity
     */
    protected function afterLogout($identity)
    {
        parent::afterLogout($identity);

        /** @var TokensStorageInterface $classStorage */
        $classStorage = $this->tokensStorageClass;
        $classStorage::disableToken($identity->getAuthKey());
    }

    /**
     * @param yii\web\IdentityInterface $identity
     * @param int $duration
     * @param null $loginMethod
     * @return bool
     */
    public function login(yii\web\IdentityInterface $identity, $duration = 0, $loginMethod = null)
    {
        if ($identity && $loginMethod) {
            $identity->setLoginMethod($loginMethod);
        }

        return parent::login($identity, $duration);
    }

    /**
     * @param null|IdentityInterface $identity
     * @param int $duration
     * @param bool $generateAuthKey - генерировать новый токен
     */
    public function switchIdentity($identity, $duration = 0, $generateAuthKey = true)
    {
        $this->setIdentity($identity);

        if ($identity && $generateAuthKey) {
            if (empty($identity->generateAuthKey())) {
                $this->setIdentity(null);
                $identity = null;
            }
        }

        if (!$this->enableSession) {
            return;
        }

        /* Ensure any existing identity cookies are removed. */
        if ($this->enableAutoLogin) {
            $this->removeIdentityCookie();
        }

        $session = Yii::$app->getSession();

        if (!YII_ENV_TEST) {
            $session->regenerateID(true);
        }

        $session->remove($this->idParam);
        $session->remove($this->authTimeoutParam);

        if ($identity) {
            $session->set($this->idParam, $identity->getAuthKey());

            if ($this->authTimeout !== null) {
                $session->set($this->authTimeoutParam, time() + $this->authTimeout);
            }
            if ($this->absoluteAuthTimeout !== null) {
                $session->set($this->absoluteAuthTimeoutParam, time() + $this->absoluteAuthTimeout);
            }
            if ($duration > 0 && $this->enableAutoLogin) {
                $this->sendIdentityCookie($identity, $duration);
            }
        } elseif ($this->enableAutoLogin) {
            $this->removeIdentityCookie();
        }
    }

    /**
     * Данный метод дублирует родительский.
     * Переопредён только для того чтобы прокинуть в findIdentity токен а не id
     *
     * @inheritdoc
     */
    protected function getIdentityAndDurationFromCookie()
    {
        $value = Yii::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);
        if (count($data) == 3) {
            list ($id, $authKey, $duration) = $data;
            /* @var $class IdentityInterface */
            $class = $this->identityClass;
            $identity = $class::findIdentity($authKey);
            if ($identity !== null) {
                if (!$identity instanceof IdentityInterface) {
                    throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
                } elseif (!$identity->validateAuthKey($authKey)) {
                    Yii::warning("Invalid auth key attempted for user '$id': $authKey", __METHOD__);
                } else {
                    return ['identity' => $identity, 'duration' => $duration];
                }
            }
        }
        $this->removeIdentityCookie();
        return null;
    }

    /**
     * Данный метод дублирует родительский.
     * Переопредён только для того switchIdentity() не генерировал новый токен
     *
     * @inheritdoc
     */
    protected function loginByCookie()
    {
        $data = $this->getIdentityAndDurationFromCookie();

        if (isset($data['identity'], $data['duration'])) {
            $identity = $data['identity'];
            $duration = $data['duration'];
            if ($this->beforeLogin($identity, true, $duration)) {
                $this->switchIdentity($identity, $this->autoRenewCookie ? $duration : 0, false);
                $id = $identity->getId();
                $ip = Yii::$app->getRequest()->getUserIP();
                Yii::info("User '$id' logged in from $ip via cookie.", __METHOD__);
                $this->afterLogin($identity, true, $duration);
            }
        }
    }

    /**
     * @see User::loginByAccessToken()
     *
     * @param string $token
     * @param int $duration
     * @param string $type
     * @return null|IdentityInterface
     */
    public function loginByAccessTokenWeb($token, $duration = 0, $type = null)
    {
        /* @var $class IdentityInterface */
        $class = $this->identityClass;
        $identity = $class::findIdentityByAccessToken($token, $type);
        if ($identity && $this->login($identity, $duration)) {
            return $identity;
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getIdentityCookieDuration()
    {
        $value = Yii::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);
        $duration = 0;

        if ($value !== null) {
            $data = json_decode($value, true);
            if (is_array($data)) {
                // последний элемент в куках это $duration
                $duration = array_pop($data);
            }
        }

        return $duration;
    }

    /**
     * @param $token
     * @return IdentityInterface
     */
    public function findByAccessToken($token)
    {
        /** @var IdentityInterface $class */
        $class = $this->identityClass;
        return $class::findIdentityByAccessToken($token);
    }

    /**
     * @param IdentityInterface $identity
     * @return bool
     */
    public function setNewUserToken(IdentityInterface $identity)
    {
        $currentToken = $this->identity->getTokenModel();

        if ($this->isGuest || !$currentToken || !$identity) {
            return false;
        }

        if ($this->getId() == $identity->getId()) {

            /** @var TokensStorageInterface $classStorage */
            $classStorage = $this->tokensStorageClass;
            $classStorage::disableToken($identity->getAuthKey());

            $identity->setLoginMethod($currentToken->login_method);

            $this->switchIdentity($identity, $this->getIdentityCookieDuration(), true);

            return true;
        }

        return false;
    }
}