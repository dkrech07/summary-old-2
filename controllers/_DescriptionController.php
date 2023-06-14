<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\User;
use app\models\SignupForm;
use app\services\SummaryService;
use app\services\DescriptionServise;

use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use app\models\ItemForm;
use app\models\Account;
use app\models\AccountForm;
use yii\widgets\ActiveForm;
use yii\web\UploadedFile;
use app\models\Summary;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;

use Orhanerday\OpenAi\OpenAi;


class DescriptionController extends Controller
{
  public function actionIndex()
  {

    // print('ok');

    // $descriptionServise = new DescriptionServise;
    // $descriptionServise->getDescription();


    // $user = Yii::$app->user->identity;
    // $summaryService = new SummaryService;
    // $itemFormModel = new ItemForm();
    // $accountFormModel = new accountForm();

    // if (\Yii::$app->request->isAjax && \Yii::$app->request->post()) {
    //     $request = Yii::$app->request;
    //     $data = $request->post();

    //     if (key($data) == 'item_id_detail') {
    //         return json_encode($summaryService->getDetailItem($data['item_id_detail']), JSON_UNESCAPED_UNICODE);
    //     }

    //     if (key($data) == 'item_id_summary') {
    //         return json_encode($summaryService->getSummmaryItem($data['item_id_summary']), JSON_UNESCAPED_UNICODE);
    //     }
    // }

    // return $this->render(
    //   'index',
    //   []
    // );
  }
}
