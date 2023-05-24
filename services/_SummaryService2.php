<?php

namespace app\services;

use Yii;
use app\models\AccountForm;
use app\models\Summary;
use app\models\ItemForm;
use app\models\Account;
use app\models\Detail;
use app\models\DetailForm;
use app\models\SummaryForm;
use yii\db\Expression;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;
use GuzzleHttp\Client;
use Orhanerday\OpenAi\OpenAi;

use Aws\Exception\AwsException;
use yii\web\UploadedFile;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;

class SummaryService
{

  // // Получает текущую дату и время;
  // public static function getCurrentDate(): string
  // {
  //   $expression = new Expression('NOW()');
  //   $now = (new \yii\db\Query)->select($expression)->scalar();
  //   return $now;
  // }











  // Выводит запись на страницу - получает подробное описание;
  public function getDescription()
  {
    // $account = Account::find()
    // ->where(['user_id' => Yii::$app->user->identity->id])
    // ->one();

    // $summaryListShortening = Summary::find()
    // ->where(['created_user' => Yii::$app->user->identity->id, 'summary_status' => 2])
    // ->all();

    if ($summaryListShortening) {
      foreach ($summaryListShortening as $item) {

        $textSummary = $this->getSummary($item->detail, $account);

        if ($textSummary) {
          $item->summary = $this->getSummary($item->detail, $account);  // Получаю краткое описание из сохраненного в элемент текста;
          $item->summary_status = 3;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
          $item->save();
          $transaction->commit();
        } catch (\Exception $e) {
          $transaction->rollBack();
          throw $e;
        } catch (\Throwable $e) {
          $transaction->rollBack();
        }
      }
    }

    $summaryListDecoding = Summary::find()
      ->where(['created_user' => Yii::$app->user->identity->id, 'summary_status' => 1])
      ->all();

    if ($summaryListDecoding) {
      // sleep(60);
      foreach ($summaryListDecoding as $item) {
        if (isset($item->decode_id)) {
          $url = "https://operation.api.cloud.yandex.net/operations/";

          $client = new Client([
            'base_uri' => $url,
          ]);

          $response = $client->request('GET', $item->decode_id, [
            'headers' => [
              'Authorization' => 'Api-Key ' . $account->api_secret_key
            ]
          ]);

          $body = $response->getBody();
          $arr_body = json_decode($body);

          if ($arr_body->done) {
            $chunksList = $arr_body->response->chunks;
            $item->detail = $arr_body->response->chunks[0]->alternatives[0]->text;
            $item->updated_at = $this->getCurrentDate();
            $item->summary_status = 2; // Получение краткого описания;
            $item->summary = $this->getSummary($chunksList[0]->alternatives[0]->text, $account);  // Получаю один из вариантов расшифровки текста;

            $newDetail = new Detail;
            $newDetail->summary_id = $item->id;
            $newDetail->detail_text = $arr_body->response->chunks[0]->alternatives[0]->text; // $chunkItem->alternatives[0]->text;

            // Сохраняю данные в таблицу Detail;
            $transaction2 = Yii::$app->db->beginTransaction();
            try {
              $newDetail->save();
              $transaction2->commit();
            } catch (\Exception $e) {
              $transaction2->rollBack();
              throw $e;
            } catch (\Throwable $e) {
              $transaction2->rollBack();
            }
          } else {
            $item->updated_at = $this->getCurrentDate();
            $item->summary_status = 1;
          }

          // Сохраняю данные в таблицу Summary;
          $transaction = Yii::$app->db->beginTransaction();
          try {
            $item->save();
            $transaction->commit();
          } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
          } catch (\Throwable $e) {
            $transaction->rollBack();
          }
        }
      }
    }
  }
}
