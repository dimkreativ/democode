<?php

namespace bosslib\yii\changeLog\traits;

use bosslib\yii\db\ActiveRecord;
use Yii;
use yii\base\Model;

/**
 * Trait ChangeLogModelTrait
 */
trait ChangeLogModelTrait
{
    /**
     * @param $actionType
     * @param null $idUser
     * @param array $arBefore
     * @param array $arAfter
     * @param array $options
     * @return array
     */
    public function actionChangeLogDiff($actionType, $idUser = null, $arBefore = [], $arAfter = [], $options = [])
    {
        return Yii::$app->changeLog->actionLogModelDiff($this, $actionType, $idUser, $arBefore, $arAfter, $options);
    }

    /**
     * @param $actionType
     * @param null $idUser
     * @param array $arData
     * @param array $options
     * @return array
     */
    public function actionChangeLog($actionType, $idUser = null, $arData = [], $options = [])
    {
        return Yii::$app->changeLog->actionLogModel($this, $actionType, $idUser, $arData, $options);
    }
}