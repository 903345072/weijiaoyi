<?php
if (common\helpers\System::isWindowsOs()) {
    Yii::setAlias('Workerman', dirname(dirname(__DIR__)) . '/vendor/workerman/workerman-for-win');
} else {
    Yii::setAlias('Workerman', dirname(dirname(__DIR__)) . '/vendor/workerman/workerman');
}
Yii::$container->set('yii\log\FileTarget', ['exportInterval' => 1]);
