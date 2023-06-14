<?php

namespace app\commands;

use Yii;
use yii\console\Controller;

// use yii\web\Controller;
use app\models\Log;
use app\services\DescriptionServise;
use app\models\Test;
use app\models\TestForm;

class LogController extends Controller
{
  public function actionWrite()
  {
    print('ok');
    // $descriptionServise = new DescriptionServise;
    // $descriptionServise->getDescription();


    // $testForm = new TestForm;
    // $testForm->test = 'test';
    // $testForm->date_add = 'test';

    // $test = new Test;
    // $test->test = $testForm->test;
    // $test->date_at = $testForm->date_add;

    // $transaction = Yii::$app->db->beginTransaction();
    // try {
    //   $test->save();
    //   $transaction->commit();
    // } catch (\Exception $e) {
    //   $transaction->rollBack();
    //   throw $e;
    // } catch (\Throwable $e) {
    //   $transaction->rollBack();
    // }
  }
}
