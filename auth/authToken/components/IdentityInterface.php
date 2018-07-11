<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 21.05.2018
 * Time: 11:22
 */

namespace bosslib\yii\auth\authToken\components;

interface IdentityInterface extends \yii\web\IdentityInterface
{
    /**
     * @param string $token
     */
    public function setAuthKey($token);

    /**
     * @param string|null $target
     */
    public function setTarget($target);

    /**
     * @return string|null
     */
    public function getTarget();

    /**
     * @return string
     */
    public function generateAuthKey();

    public function setTokenModel($modelToken);

    public function getTokenModel();

    /**
     * @param string|null $method
     */
    public function setLoginMethod($method);

    /**
     * @return string|null
     */
    public function getLoginMethod();

    /**
     * @param int|string $id
     * @return IdentityInterface
     */
    public static function findIdentity($id);

    /**
     * @param mixed $token
     * @param null $type
     * @return IdentityInterface
     */
    public static function findIdentityByAccessToken($token, $type = null);
}