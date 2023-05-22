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
  /**
   * @return string
   */
  public static function getCurrentDate(): string
  {
    $expression = new Expression('NOW()');
    $now = (new \yii\db\Query)->select($expression)->scalar();
    return $now;
  }

  public function getSummaryItems()
  {
    return Summary::find()
      ->orderBy('id DESC')
      ->joinWith('summaryStatus');
  }

  public function getSummary($data)
  {
    $open_ai_key = ''; //getenv('OPENAI_API_KEY');
    $open_ai = new OpenAi($open_ai_key);

    $chat = $open_ai->chat([
      'model' => 'gpt-3.5-turbo',
      'messages' => [
        // [
        //     "role" => "system",
        //     "content" => "You are a helpful assistant."
        // ],
        [
          "role" => "user",
          "content" => "Сделай краткое описание из текста: " . $data,
        ],
        // [
        //     "role" => "assistant",
        //     "content" => "The Los Angeles Dodgers won the World Series in 2020."
        // ],
        // [
        //     "role" => "user",
        //     "content" => "Where was it played?"
        // ],
      ],
      // 'temperature' => 1.0,
      // 'max_tokens' => 4000,
      // 'frequency_penalty' => 0,
      // 'presence_penalty' => 0,
    ]);

    $d = json_decode($chat);

    // // return $chat['choices'];
    // var_dump($chat);
    // print('<br>');
    // print('<br>');
    // var_dump($d->choices[0]->message->content);
    // print('<br>');
    // print('<br>');
    // var_dump($chat);

    // print('<br>');
    // print('<br>');
    // print($d['stdClass']);

    // print($d['id']);
    // exit;
    return $d->choices[0]->message->content;
  }

  public function getDescription()
  {
    $account = Account::find()
      ->where(['user_id' => Yii::$app->user->identity->id])
      ->one();

    for ($i = 0; $i < 30; $i++) {

      $summaryList = Summary::find()
        ->where(['created_user' => Yii::$app->user->identity->id, 'summary_status' => 1])
        ->all();

      if ($summaryList) {
        foreach ($summaryList as $item) {
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
              $item->summary_status = 2;

              $item->summary = $this->getSummary($chunksList[0]->alternatives[0]->text);

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

              foreach ($chunksList as $chunkItem) {
                $newDetail = new Detail;

                $newDetail->summary_id = $item->id;
                $newDetail->detail_text = $chunkItem->alternatives[0]->text;

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

                // if ($number === 0) {
                //   getSummary($data)

                //   $item->summary = getSummary($newDetail->detail_text);

                //   $transaction = Yii::$app->db->beginTransaction();
                //   try {
                //     $item->save();
                //     $transaction->commit();
                //   } catch (\Exception $e) {
                //     $transaction->rollBack();
                //     throw $e;
                //   } catch (\Throwable $e) {
                //     $transaction->rollBack();
                //   }

                // }
              }
            }
          } else {
            $item->updated_at = $this->getCurrentDate();
            $item->summary_status = 4;

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
      } else {
        return true;
      }

      if ($i > 0) {
        sleep(10);
      }
    }
  }

  public function decodeAudio($yandexStorageFile, $extension, $account)
  {

    $extensionsMap = [
      'pcm' => 'LINEAR16_PCM',
      'ogg' => 'OGG_OPUS',
      'mp3' => 'MP3',
    ];

    $url = "https://transcribe.api.cloud.yandex.net";

    $client = new Client([
      'base_uri' => $url,
    ]);

    $response = $client->request('POST', '/speech/stt/v2/longRunningRecognize', [
      'headers' => [
        'Authorization' => 'Api-Key ' . $account->api_secret_key
      ],
      'json' => [
        'config' => [
          'specification' => [
            'languageCode' => 'ru-RU',
            'model' => 'general',
            // 'profanityFilter' => true,
            'audioEncoding' => $extensionsMap[$extension],
            // 'sampleRateHertz' => '48000',
            // 'audioChannelCount' => '1',
            'literature_text' => true,
          ]
        ],
        'audio' => [
          'uri' => 'https://storage.yandexcloud.net/' . $yandexStorageFile,
        ]
      ]
    ]);

    $body = $response->getBody();
    $arr_body = json_decode($body);
    return $arr_body->id;
  }

  public function uploadYandexStorage($uploadPath, $fileName, $account)
  {
    $sharedConfig = [
      'credentials' => [
        'key' => $account->y_key_id,
        'secret' => $account->y_secret_key,
      ],
      'version' => 'latest',
      'endpoint' => 'https://storage.yandexcloud.net',
      'region' => 'ru-central1',
    ];

    $s3Client = new S3Client($sharedConfig);

    $uploader = new MultipartUploader($s3Client, $uploadPath, [
      'bucket' => $account->bucket_name,
      'key' => $fileName,
    ]);

    try {
      $result = $uploader->upload();
      echo "Upload complete: {$result['ObjectURL']}\n";
    } catch (MultipartUploadException $e) {
      echo $e->getMessage() . "\n";
    }

    unlink($uploadPath);
    return $account->bucket_name . '/' . $fileName;
  }

  public function createItem(ItemForm $itemFormModel)
  {
    $account = Account::find()
      ->where(['user_id' => Yii::$app->user->identity->id])
      ->one();

    $itemsCount = Summary::find()
      ->where(['created_user' => Yii::$app->user->identity->id])
      ->count();

    $newItem = new Summary;

    if ($itemFormModel->file) {
      $fileName = substr(md5(microtime() . rand(0, 9999)), 0, 8) . '.' . $itemFormModel->file->extension;
      $uploadPath = './upload' . '/' . $fileName;
      $itemFormModel->file->saveAs($uploadPath);
      $newItem->file = $this->uploadYandexStorage($uploadPath, $fileName, $account);

      $newItem->decode_id = $this->decodeAudio($newItem->file, $itemFormModel->file->extension, $account);
      $newItem->summary_status = 1;
    } else {
      $newItem->summary_status = 2;
    }

    $newItem->number = $itemsCount + 1;
    $newItem->title = $itemFormModel->title;
    $newItem->detail = $itemFormModel->detail;
    $newItem->summary = $itemFormModel->summary;
    $newItem->created_user = Yii::$app->user->identity->id;
    $newItem->created_at = $this->getCurrentDate();
    $newItem->updated_at = $this->getCurrentDate();

    $transaction = Yii::$app->db->beginTransaction();
    try {
      $newItem->save();
      $transaction->commit();
    } catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    } catch (\Throwable $e) {
      $transaction->rollBack();
    }
  }

  public function getDetailItem($data)
  {
    // $detailFormModel = new ItemForm();

    // $editSummaryItem = Summary::find()
    //   ->where(['id' => $data])
    //   ->one();

    $detailItems = Detail::find()
      ->where(['summary_id' => $data])
      ->all();

    $summaryItem = Summary::find()
      ->where(['id' => $data])
      ->one();

    $detailItemsList = [];

    foreach ($detailItems as $detailItem) {
      $detailForm = new DetailForm;

      $detailForm->summary_id = $detailItem->summary_id;
      $detailForm->title = $summaryItem->title;
      $detailForm->detail_text = $detailItem->detail_text;
      // $detailForm->detail = $itemFormModel->detail;

      $detailItemsList[] = $detailForm;
    }

    // $detail = new DetailForm;

    // $detailFormModel->title = $editSummaryItem->title;
    // $detailFormModel->detail = $editSummaryItem->detail;

    return $detailItemsList;
  }

  public function getSummmaryItem($data)
  {
    $summaryItem =  Summary::find()
      ->where(['id' => $data])
      ->one();

    $summaryForm = new SummaryForm;

    $summaryForm->summary_id = $summaryItem->id;
    $summaryForm->title = $summaryItem->title;
    $summaryForm->summary_text = $summaryItem->summary;

    return $summaryForm;
  }

  public function editAccount(AccountForm $accountFormModel)
  {
    $editAccount = Account::find()
      ->where(['user_id' => Yii::$app->user->identity->id])
      ->one();

    if (!$editAccount) {
      $editAccount = new Account;
      $editAccount->user_id = Yii::$app->user->identity->id;
    }

    $editAccount->api_secret_key = $accountFormModel->api_secret_key;
    $editAccount->y_key_id = $accountFormModel->y_key_id;
    $editAccount->y_secret_key = $accountFormModel->y_secret_key;
    $editAccount->bucket_name = $accountFormModel->bucket_name;

    $transaction = Yii::$app->db->beginTransaction();
    try {
      $editAccount->save();
      $transaction->commit();
    } catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    } catch (\Throwable $e) {
      $transaction->rollBack();
    }
  }

  public function uploadFile()
  {
    $fileName = 'file';
    $uploadPath = './upload';
    if (isset($_FILES[$fileName])) {
      $file = \yii\web\UploadedFile::getInstanceByName($fileName);

      // $fileName = uniqid('file_') . '.' . $file->extension;
      $fileName = substr(md5(microtime() . rand(0, 9999)), 0, 8) . '.' . $file->extension;
      $uploadPath = $uploadPath . '/' . $fileName;

      if ($file->saveAs($uploadPath)) {
        //Now save file data to database
        echo \yii\helpers\Json::encode($file);

        // Отпрвляем файл в Яндекс Object Storage
        $user = Yii::$app->user->identity;
        $account = Account::find()
          ->where(['user_id' => $user->id])
          ->one();

        $sharedConfig = [
          'credentials' => [
            'key' => $account->y_key_id,
            'secret' => $account->y_secret_key,
          ],
          'version' => 'latest',
          'endpoint' => 'https://storage.yandexcloud.net',
          'region' => 'ru-central1',
        ];

        $s3Client = new S3Client($sharedConfig);

        // Use multipart upload
        $uploader = new MultipartUploader($s3Client, $uploadPath, [
          'bucket' => $account->bucket_name,
          'key' => $fileName,
        ]);

        try {
          $result = $uploader->upload();
          echo "Upload complete: {$result['ObjectURL']}\n";
        } catch (MultipartUploadException $e) {
          echo $e->getMessage() . "\n";
        }
      }
    }

    return false;
  }

  public function DetailEdit(ItemForm $detailModel)
  {

    $editSummaryItem = Summary::find()
      ->where(['id' => $detailModel->id])
      ->one();
    //   $editCustom = Customs::find()
    //     ->where(['ID' => $customEditFormModel->ID])
    //     ->one();

    //   $editCustom->CODE = $customEditFormModel->CODE;
    //   $editCustom->NAMT = $customEditFormModel->NAMT;
    //   $editCustom->OKPO = $customEditFormModel->OKPO;
    //   $editCustom->OGRN = $customEditFormModel->OGRN;
    //   $editCustom->INN = $customEditFormModel->INN;
    //   $editCustom->NAME_ALL = $customEditFormModel->NAME_ALL;
    //   $editCustom->ADRTAM = $customEditFormModel->ADRTAM;
    //   $editCustom->PROSF = $customEditFormModel->PROSF;
    //   $editCustom->TELEFON = $customEditFormModel->TELEFON;
    //   $editCustom->FAX = $customEditFormModel->FAX;
    //   $editCustom->EMAIL = $customEditFormModel->EMAIL;
    //   $editCustom->COORDS_LATITUDE = $customEditFormModel->COORDS_LATITUDE;
    //   $editCustom->COORDS_LONGITUDE = $customEditFormModel->COORDS_LONGITUDE;

    //   $transaction = Yii::$app->db->beginTransaction();
    //   try {
    //     $editCustom->save();
    //     $transaction->commit();
    //   } catch (\Exception $e) {
    //     $transaction->rollBack();
    //     throw $e;
    //   } catch (\Throwable $e) {
    //     $transaction->rollBack();
    //   }
  }

  // public function getEditPage($id)
  // {
  //   $pageEditFormModel = new PageEditFormModel();

  //   $editPage = Pages::find()
  //     ->where(['page_url' => $id])
  //     ->one();

  //   $pageEditFormModel->id = $editPage->id;
  //   $pageEditFormModel->page_dt_add = $editPage->page_dt_add;
  //   $pageEditFormModel->page_name = $editPage->page_name;
  //   $pageEditFormModel->page_meta_description = $editPage->page_meta_description;
  //   $pageEditFormModel->page_content = $editPage->page_content;
  //   $pageEditFormModel->page_user_change = $editPage->page_user_change;
  //   $pageEditFormModel->page_url = $editPage->page_url;

  //   return $pageEditFormModel;
  // }

  // /**
  //  * @param int $id
  //  * 
  //  * @return Summary|null
  //  */
  // public function getSummary(int $id): ?Summary
  // {
  //   return Summary::find()
  //     ->joinWith('city', 'category')
  //     ->where(['tasks.id' => $id])
  //     ->one();
  // }

  // public function editItem(ItemForm $itemtFormModel)
  // {
  //   $editItem = Summary::find()
  //     ->where(['id' => $itemtFormModel->id])
  //     ->one();

  //   $item = new ItemForm();
  // }
}
