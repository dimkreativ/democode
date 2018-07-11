<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 01.06.2018
 * Time: 14:54
 */

namespace bosslib\yii\changeLog\models;

interface ChangeLogInterface
{
    /**
     * @param $idUser
     * @param $objectType
     * @param $objectId
     * @param $actionType
     * @param array $diffJson
     *
     * @return bool
     */
    public function saveLog($idUser, $objectType, $objectId, $actionType, array $diffJson = []);
}