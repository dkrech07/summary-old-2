<?php

namespace app\controllers;

use Yii;
use yii\helpers\Url;

use yii\web\Controller;
use app\services\DescriptionServise;

class CronController extends Controller
{
  // public $message;

  // public function options($actionID)
  // {
  //   return ['message'];
  // }

  // public function optionAliases()
  // {
  //   return ['m' => 'message'];
  // }

  public function actions()
  {
    return [
      'error' => [
        'class' => 'yii\web\ErrorAction',
      ],
    ];
  }

  public function actionCron()
  {
    // echo $this->message . "\n";
    // print('ok');
    $descriptionServise = new DescriptionServise;
    $descriptionServise->getDescription();
  }
}
