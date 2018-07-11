<?php

use yii\db\Migration;

/**
 * Class m180518_080231_users_auth_tokens_tables
 */
class m180518_080231_users_auth_tokens_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('users_auth_tokens', [
            'id' => $this->bigPrimaryKey(),
            'id_user' => $this->bigInteger()->notNull(),
            'token' => $this->string(255)->notNull(),
            'public_token' => $this->string(255)->notNull(),
            'push_token' => $this->string(255),
            'target' => $this->string(100),
            'date_created' => $this->dateTime(),
            'active' => $this->tinyInteger()->notNull(),
            'date_last_used' => $this->dateTime(),
            'user_agent' => $this->text(),
            'ip' => $this->string(50),
            'url' => $this->text(),
            'login_method' => $this->string(50),
        ]);

        $this->createIndex('idx_users_auth_tokens_id_user', 'users_auth_tokens', ['id_user']);
        $this->createIndex('idx_users_auth_tokens_token', 'users_auth_tokens', ['token']);
        $this->createIndex('idx_users_auth_tokens_active', 'users_auth_tokens', ['active']);
        $this->createIndex('idx_users_auth_tokens_public_token', 'users_auth_tokens', ['public_token']);
        $this->createIndex('idx_users_auth_tokens_target', 'users_auth_tokens', ['target']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('users_auth_tokens');
    }
}
