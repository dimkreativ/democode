<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 01.06.2018
 * Time: 11:46
 */

namespace bosslib\yii\changeLog\models;

use yii\db\Expression;

/**
 * This is the model class for table "change_log_behavior".
 *
 * @property int $id
 * @property int $id_user
 * @property string $object_type
 * @property int $object_id
 * @property string $action_type
 * @property string $change_datetime
 * @property string $diff_json
 */
class ChangeLog extends \yii\db\ActiveRecord implements ChangeLogInterface
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'change_log_action';
    }

    /**
     * {@inheritdoc}
     */
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
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_user' => 'Id User',
            'object_type' => 'Object Type',
            'object_id' => 'Object ID',
            'action_type' => 'Action Type',
            'change_datetime' => 'Change Datetime',
            'diff_json' => 'Diff Json',
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
        if (empty($diffJson)) {
            return false;
        }

        if (!$diffJson = json_encode($diffJson, JSON_UNESCAPED_UNICODE)) {
            return false;
        }

        $this->change_datetime = new Expression('NOW()');

        $this->id_user = $idUser;
        $this->object_type = $objectType;
        $this->object_id = $objectId;
        $this->action_type = $actionType;
        $this->diff_json = $diffJson;

        return $this->save();
    }
}