<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 29.05.2018
 * Time: 11:10
 */
namespace bosslib\yii\changeLog\behaviors;

use bosslib\yii\changeLog\models\ChangeLogInterface;
use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\base\InvalidValueException;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;

/**
 * Class ChangeLogBehavior
 * @package bosslib\yii\behaviors\ChangeLog
 * @property ActiveRecord|ChangeLogInterface $owner
 * @property array $excludedAttributes
 * @property boolean $parseStringLines
 * @property string $replaceMarkerName
 * @property array $allowAttributes
 * @property string $logClass
 */
class ChangeLogBehavior extends Behavior
{
    const ACTION_INSERT = 'insert';
    const ACTION_UPDATE = 'update';

    /**
     * @var string
     */
    public $replaceMarkerName = 'replace';

    /**
     * @var array
     */
    public $allowAttributes = [];

    /**
     * @var array
     */
    public $excludedAttributes = [];

    /**
     * @var bool Флаг, обрабатывать большой текст построчно
     */
    public $parseStringLines = true;

    /**
     * @var string|null
     */
    public $logClass = null;

    private $isInsert = false;

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'getDiffInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'getDiffUpdate'
        ];
    }

    /**
     * @param Event $event
     */
    public function getDiffInsert(Event $event)
    {
        $this->isInsert = true;

        return $this->getDiff($event);
    }

    /**
     * @param Event $event
     */
    public function getDiffUpdate(Event $event)
    {
        $this->isInsert = false;

        return $this->getDiff($event);
    }

    /**
     * @param Event|AfterSaveEvent $event
     */
    public function getDiff(Event $event)
    {
        $diff = [];

        foreach ($event->changedAttributes as $attrName => $attrVal) {
            $newAttrVal = $this->owner->getAttribute($attrName);

            if (!empty($this->allowAttributes) && !in_array($attrName, $this->allowAttributes)) {
                continue;
            }

            if (in_array($attrName, $this->excludedAttributes)) {
                continue;
            }

            if ($newAttrVal !== $attrVal) {
                $diff[$attrName] = $this->logPreparation($attrVal, $newAttrVal);
            }
        }

        $this->saveLog($diff);
    }

    /**
     * @param string $oldData
     * @param string $newData
     * @return array
     */
    protected function logPreparation($oldData, $newData)
    {
        return Yii::$app->changeLog->diffString($oldData, $newData, [
            'parseStringLines' => $this->parseStringLines,
            'replaceMarkerName' => $this->replaceMarkerName,
        ]);
    }

    /**
     * @param $diff
     * @return mixed
     */
    protected function saveLog($diff)
    {
        $diff = array_filter($diff, function ($v) {
            return !empty($v);
        });

        if (empty($diff) || !is_array($diff)) {
            return false;
        }

        $logComponent = Yii::$app->changeLog->getLogComponent($this->logClass);

        if (!$logComponent instanceof ChangeLogInterface) {
            throw new InvalidValueException("$this->logClass must return an object implementing IdentityInterface.");
        }

        return $logComponent->saveLog(Yii::$app->getUser()->getId(), $this->owner::tableName(), $this->owner->primaryKey, $this->getActionType(), $diff);
    }

    /**
     * @return string
     */
    protected function getActionType()
    {
        if (method_exists($this->owner, 'getLogActionType')) {
            return $this->owner->getLogActionType();
        }

        return $this->isInsert ? static::ACTION_INSERT : static::ACTION_UPDATE;
    }
}