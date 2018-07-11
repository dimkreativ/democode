<?php

use yii\db\Migration;

/**
 * Class m180601_082841_logs_model
 */
class m180601_082841_logs_model extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('change_log_action', [
            'id' => $this->primaryKey(),
            'id_user' => $this->integer(11)->null(),
            'object_type' => $this->string(100)->null(),
            'object_id' => $this->integer(11)->null(),
            'action_type' => $this->string(100)->null(),
            'change_datetime' => $this->dateTime(),
            'diff_json' => $this->text(),
        ]);

        $this->createIndex('idx_change_log_behavior_id_user', 'change_log_action', ['id_user']);
        $this->createIndex('idx_change_log_behavior_object_type', 'change_log_action', ['object_type']);
        $this->createIndex('idx_change_log_behavior_object_id', 'change_log_action', ['object_id']);
        $this->createIndex('idx_change_log_behavior_action_type', 'change_log_action', ['action_type']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('change_log_action');
    }
}
