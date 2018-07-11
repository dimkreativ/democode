<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 1.06.2018
 * Time: 16:48
 */

namespace bosslib\yii\changeLog\commands;

use bosslib\yii\changeLog\models\ChangeLogRedis;
use bosslib\yii\commands\BaseCommand;

class RedisCommand extends BaseCommand
{
    /**
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function actionRedisMigrationData()
    {
        ChangeLogRedis::redisMigrationData();
    }
}