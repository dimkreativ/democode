<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 1.06.18
 * Time: 16:14
 */

namespace bosslib\yii\changeLog\models;

use bosslib\yii\redis\Model;
use Yii;

/**
 * @property int $id_user
 * @property string $object_type
 * @property int $object_id
 * @property string $action_type
 * @property string $change_datetime
 * @property string $diff_json
 */
class ChangeLogRedis extends Model implements ChangeLogInterface
{
    public $id_user;
    public $object_type;
    public $object_id;
    public $action_type;
    public $change_datetime;
    public $diff_json;

    public static function getDb()
    {
        return Yii::$app->get('redisModel');
    }

    public static function isMultiple()
    {
        return true;
    }

    public static function primaryKey()
    {
        return ['id_user'];
    }

    public function rules()
    {
        return [
            [['id_user', 'object_id'], 'default', 'value' => null],
            [['id_user', 'object_id'], 'integer'],
            [['change_datetime'], 'safe'],
            [['diff_json'], 'string'],
            [['object_type', 'action_type'], 'string', 'max' => 100],
        ];
    }

    /**
     * @param $idUser
     * @param $objectType
     * @param $objectId
     * @param $actionType
     * @param array $diffJson
     *
     * @return bool
     */
    public function saveLog($idUser, $objectType, $objectId, $actionType, array $diffJson = [])
    {
        if (!$idUser) {
            return false;
        }

        if (!empty($diffJson)) {
            if (!$diffJson = json_encode($diffJson, JSON_UNESCAPED_UNICODE)) {
                return false;
            }
        } else {
            $diffJson = '';
        }

        $this->change_datetime = date("Y-m-d H:i:s");

        $this->id_user = $idUser;
        $this->object_type = $objectType;
        $this->object_id = $objectId;
        $this->action_type = $actionType;
        $this->diff_json = $diffJson;
        $this->withLog(true);

        return $this->save();
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @internal
     */
    public static function redisMigrationData()
    {
        $logs = static::getLogsByKey(static::buildKeyForLog(), 1000);
        $logs = array_unique($logs);

        $redis = static::getDb();

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            $for_delete = [];

            foreach ($logs as $log) {
                $values = $redis->get($log);

                if (empty($values)) {
                    $for_delete[] = $log;
                    continue;
                }

                foreach (json_decode($values, true) as $val) {
                    $m = new ChangeLog();

                    $m->id_user = $val['id_user'];
                    $m->object_type = $val['object_type'];
                    $m->object_id = $val['object_id'];
                    $m->action_type = $val['action_type'];
                    $m->diff_json = $val['diff_json'];
                    $m->change_datetime = $val['change_datetime'];

                    $m->save();
                }

                $for_delete[] = $log;
            }

            $transaction->commit();

            $redis->executeCommand('MULTI');

            foreach ($for_delete as $f_del) {
                $redis->lrem(static::buildKeyForLog(), 0, $f_del);
                $redis->del($f_del);
            }

            $redis->executeCommand('EXEC');
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
