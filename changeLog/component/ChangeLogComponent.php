<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 04.06.2018
 * Time: 15:01
 */

namespace bosslib\yii\changeLog\component;

use bosslib\yii\changeLog\models\ChangeLogInterface;
use Diff;
use Diff_Renderer_Html_Array;
use Yii;
use yii\base\Component;
use yii\db\ActiveRecord;

class ChangeLogComponent extends Component
{
    public $logClass = 'bosslib\yii\changeLog\models\ChangeLogRedis';

    /**
     * @var string - Маркер для замены в тексте до и после
     */
    public $replaceMarkerName = 'replace';

    /**
     * @var bool Флаг, обрабатывать большой текст построчно
     */
    public $parseStringLines = true;

    /**
     * @var ChangeLogInterface[]
     */
    private $_logComponent = [];

    /**
     * @param null $logClass - bosslib\yii\changeLog\models\ChangeLogRedis
     * @return ChangeLogInterface
     */
    public function getLogComponent($logClass = null)
    {
        return $this->createLogComponent(($logClass ? $logClass : $this->logClass));
    }

    /**
     * @param $logClass
     * @return ChangeLogInterface
     */
    protected function createLogComponent($logClass)
    {
        if (!isset($this->_logComponent[$logClass])) {
            $this->_logComponent[$logClass] = new $logClass;
        }

        return $this->_logComponent[$logClass];
    }

    /**
     * @param $oldString
     * @param $newString
     * @param array $options
     * @return array
     */
    public function diffString($oldString, $newString, $options = [])
    {
        $parseStringLines = isset($options['parseStringLines']) ? $options['parseStringLines'] : $this->parseStringLines;
        $replaceMarkerName = isset($options['replaceMarkerName']) ? $options['replaceMarkerName'] : $this->replaceMarkerName;

        $oldString = $parseStringLines ? $this->lineToArray($oldString) : [(string) $oldString];
        $newString = $parseStringLines ? $this->lineToArray($newString) : [(string) $newString];

        $logsData = (new Diff($oldString, $newString))->render(new Diff_Renderer_Html_Array());

        $resLog = [];

        $lineNumber = 1;

        foreach ($logsData[0] as $keyBlock => $block) {
            $base = $block['base']; // базовая строка
            $changed = $block['changed']; // строка с изменениями

            if ($block['tag'] == 'equal') {
                foreach ($base['lines'] as $kLine => $line) {
                    $resLog[$lineNumber] = $this->getEmptyValue($replaceMarkerName);
                    $resLog[$lineNumber]['value'] = $line;
                    $lineNumber++;
                }

                continue;
            }

            foreach ($base['lines'] as $kLine => $line) {
                $matches = [];

                $resLog[$lineNumber] = $this->getEmptyValue($replaceMarkerName);

                // Проверяем, мб что то удалено
                if (preg_match_all('/<del>(.+)?<\/del>/iU', $line, $matches, PREG_PATTERN_ORDER)) {
                    $replaceArray = $this->getReplaceArray($matches[0], $replaceMarkerName);

                    $resLog[$lineNumber] = [
                        'value' => str_replace($replaceArray, array_flip($replaceArray), $line),
                        'base' => $this->getReplaceArray($matches[1], $replaceMarkerName),
                    ];
                } else {
                    // если изменилась строка целиком
                    $resLog[$lineNumber]['base'] = $this->getReplaceArray([$line], $replaceMarkerName);
                }

                if (isset($changed['lines'][$kLine])) {
                    $matchesChanged = [];
                    $changedLine = $changed['lines'][$kLine];

                    if (preg_match_all('/<ins>(.+)?<\/ins>/iU', $changedLine, $matchesChanged, PREG_PATTERN_ORDER)) {
                        $resLog[$lineNumber]['changed'] = $this->getReplaceArray($matchesChanged[1], $replaceMarkerName);
                    } else {
                        $resLog[$lineNumber]['changed'] = $this->getReplaceArray([$changedLine], $replaceMarkerName);
                    }

                    unset($changed['lines'][$kLine]);
                }

                $lineNumber++;
            }

            // Если изменений больше
            if (!empty($changed['lines'])) {
                foreach ($changed['lines'] as $kLine => $line) {
                    $lineNumber++;

                    $resLog[$lineNumber] = $this->getEmptyValue($replaceMarkerName);

                    $matchesChanged = [];

                    if (preg_match_all('/<ins>(.+)?<\/ins>/iU', $line, $matchesChanged, PREG_PATTERN_ORDER)) {
                        $resLog[$lineNumber]['changed'] = $this->getReplaceArray($matchesChanged[1], $replaceMarkerName);
                    } else {
                        $resLog[$lineNumber]['changed'] = $this->getReplaceArray([$line], $replaceMarkerName);
                    }
                }
            }
        }

        return $resLog;
    }

    protected function lineToArray($string)
    {
        return explode("\n", str_replace("\r\n", "\n", $string));
    }

    /**
     * @param $array
     * @param string $replaceMarkerName
     * @return array
     */
    protected function getReplaceArray($array, $replaceMarkerName)
    {
        $res = [];

        foreach ($array as $k => $v) {
            $res['{'.$replaceMarkerName.'_'.$k.'}'] = $v;
        }

        return $res;
    }

    /**
     * @param string $replaceMarkerName
     * @return array
     */
    protected function getEmptyValue($replaceMarkerName)
    {
        return [
            'value' => '{'.$replaceMarkerName.'_0}',
            'base' => [],
            'changed' => []
        ];
    }

    /**
     * @param array $arBefore
     * @param array $arAfter
     * @return array
     */
    protected function preparationArray($arBefore = [], $arAfter = [])
    {
        if (!is_array($arBefore)) {
            $arBefore = [];
        }

        if (!is_array($arAfter)) {
            $arAfter = [];
        }

        // Прогон массива "до"
        foreach ($arBefore as $key => $val) {
            if (!isset($arAfter[$key])) {
                $arAfter[$key]='';
            }

            if ($arAfter[$key] == $val){
                unset($arAfter[$key]);
                unset($arBefore[$key]);
            }
        }

        // Прогон массива "после"
        foreach ($arAfter as $key =>$val) {
            if (!isset($arBefore[$key])) {
                $arBefore[$key] = '';
            }
        }

        return [$arBefore, $arAfter];
    }

    /**
     * Пишем лог для моделей
     *
     * @param ActiveRecord $model
     * @param $actionType
     * @param null $idUser
     * @param array $arBefore
     * @param array $arAfter
     * @param array $options
     *
     * @return array
     */
    public function actionLogModelDiff(ActiveRecord $model, $actionType, $idUser = null, $arBefore = [], $arAfter = [], $options = [])
    {
        return $this->actionLogDiff($model::tableName(), $model->primaryKey, $actionType, $idUser, $arBefore, $arAfter, $options);
    }

    /**
     * Пишем лог для моделей без сравнения
     *
     * @param ActiveRecord $model
     * @param $actionType
     * @param null $idUser
     * @param array $arData
     * @param array $options
     * @return array
     */
    public function actionLogModel(ActiveRecord $model, $actionType, $idUser = null, $arData = [], $options = [])
    {
        return $this->actionLog($model::tableName(), $model->primaryKey, $actionType, $idUser, $arData, $options);
    }

    /**
     * @param $objectType
     * @param $objectId
     * @param $actionType
     * @param null $idUser
     * @param array $arBefore
     * @param array $arAfter
     * @param array $options
     *
     * @return array
     */
    public function actionLogDiff($objectType, $objectId, $actionType, $idUser = null, $arBefore = [], $arAfter = [], $options = [])
    {
        $idUser = ($idUser !== null) ? $idUser : Yii::$app->getUser()->getId();

        $arr = $this->preparationArray($arBefore, $arAfter);

        $arBefore = $arr[0];
        $arAfter = $arr[1];

        $diff = [];

        foreach($arBefore as $k => $v) {
            $diff[$k] = $this->diffString($v, $arAfter[$k]);
        }

        $this
            ->getLogComponent((isset($options['logClass']) ? $options['logClass'] : null))
            ->saveLog($idUser, $objectType, $objectId, $actionType, $diff);

        return $diff;
    }

    /**
     * @param $objectType
     * @param $objectId
     * @param $actionType
     * @param null $idUser
     * @param array $arData
     * @param array $options
     *
     * @return array
     */
    public function actionLog($objectType, $objectId, $actionType, $idUser = null, $arData = [], $options = [])
    {
        $idUser = ($idUser !== null) ? $idUser : Yii::$app->getUser()->getId();

        $this
            ->getLogComponent((isset($options['logClass']) ? $options['logClass'] : null))
            ->saveLog($idUser, $objectType, $objectId, $actionType, $arData);

        return $arData;
    }
}