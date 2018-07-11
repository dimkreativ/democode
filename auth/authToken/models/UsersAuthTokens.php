<?php

namespace bosslib\yii\auth\authToken\models;

use Yii;
use yii\db\Expression;

/**
 * This is the model class for table "users_auth_tokens".
 *
 * @property int $id
 * @property int $id_user
 * @property string $token
 * @property string $public_token
 * @property string $push_token
 * @property string $target
 * @property string $date_created
 * @property int $active
 * @property string $date_last_used
 * @property string $user_agent
 * @property string $ip
 * @property string $url
 * @property string $login_method
 */
class UsersAuthTokens extends \yii\db\ActiveRecord implements TokensStorageInterface
{
    const ACTIVE = 1;
    const NOT_ACTIVE = 0;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'users_auth_tokens';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'token', 'public_token', 'active'], 'required'],
            [['id_user', 'active'], 'default', 'value' => null],
            [['id_user', 'active'], 'integer'],
            [['date_created', 'date_last_used'], 'safe'],
            [['user_agent', 'url'], 'string'],
            [['token', 'public_token', 'push_token'], 'string', 'max' => 255],
            [['target'], 'string', 'max' => 100],
            [['ip', 'login_method'], 'string', 'max' => 50],
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
            'token' => 'Token',
            'public_token' => 'Public Token',
            'push_token' => 'Push Token',
            'target' => 'Target',
            'date_created' => 'Date Created',
            'active' => 'Active',
            'date_last_used' => 'Date Last Used',
            'user_agent' => 'User Agent',
            'ip' => 'Ip',
            'url' => 'Url',
            'login_method' => 'Login Method',
        ];
    }

    /**
     * @param $idUser
     * @param array $data
     * @return bool|string
     */
    public static function createToken($idUser, $data = [])
    {
        $token  = new static();

        $token->id_user = $idUser;
        $token->user_agent = Yii::$app->request->userAgent;
        $token->ip = Yii::$app->request->userIP;
        $token->url = Yii::$app->request->referrer;
        $token->token = $token->generateRandomToken();
        $token->public_token = $token->generateRandomToken();
        $token->date_created = new Expression('NOW()');
        $token->active = static::ACTIVE;

        $token->load($data, '');

        if ($token->save()) {
            return $token->token;
        }

        return false;
    }

    /**
     * @param int $length
     * @return string
     * @throws \yii\base\Exception
     */
    public function generateRandomToken($length = 32)
    {
        $token = Yii::$app->security->generateRandomString($length);

        $isToken = static::find()->where([
            'or',
            ['token' => $token],
            ['public_token' => $token]
        ])->scalar();

        if(!$isToken) {
            return $token;
        } else {
            return $this->generateRandomToken($length);
        }
    }

    public static function disableToken($token)
    {
        if (!empty($token)) {
            static::updateAll(['active' => self::NOT_ACTIVE, 'date_last_used' => date('Y-m-d H:i:s')], ['token' => $token]);
        }
    }

    /**
     * @param $token
     * @param null $type
     * @return array|null|\yii\db\ActiveRecord
     */
    public static function getActiveToken($token, $type = null)
    {
        $model = self::find()->where([
            'and',
            [
                'token' => $token,
                'active' => self::ACTIVE
            ]
        ]);

        if ($type !== null) {
            $model->andWhere(['target' => $type]);
        }

        return $model->one();
    }
}
