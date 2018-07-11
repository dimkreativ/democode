<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 21.05.2018
 * Time: 13:41
 */

namespace bosslib\yii\auth\authToken\models;


interface TokensStorageInterface
{
    /**
     * @param $idUser
     * @param array $data
     * @return bool|string
     */
    public static function createToken($idUser, $data = []);

    /**
     * @param string $token
     */
    public static function disableToken($token);

    public static function getActiveToken($token, $type = null);
}