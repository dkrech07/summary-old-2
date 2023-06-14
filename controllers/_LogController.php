<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\services\DescriptionServise;

class LogController extends Controller
{
  public function actionWrite()
  {
    $descriptionServise = new DescriptionServise;
    $descriptionServise->getDescription();
  }
}
