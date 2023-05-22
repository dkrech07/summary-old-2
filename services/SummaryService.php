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

    // Получает текущую дату и время;
    public static function getCurrentDate(): string
    {
      $expression = new Expression('NOW()');
      $now = (new \yii\db\Query)->select($expression)->scalar();
      return $now;
    }

    // Загружает аудиозапись в Яндекс Storage;
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

    // Выводит список записей;
    public function getSummaryItems()
    {
      return Summary::find()
        ->orderBy('id DESC')
        ->joinWith('summaryStatus');
    }

    // Выводит подробное описание;
    public function getDetailItem($data)
    {
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
        $detailItemsList[] = $detailForm;
      }
  
      return $detailItemsList;
    }

    // Выводит краткое описание;
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

    // Получает подробное описание от Яндекс SpeechKit;
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

    // Получает краткое описание от Chat GPT;
    public function getSummary($data, $account)
    {
      $open_ai_key = $account->openai_api_key; //getenv('OPENAI_API_KEY');
      $open_ai = new OpenAi($open_ai_key);
  
      $chat = $open_ai->chat([
        'model' => $account->openai_chat_model, //'gpt-3.5-turbo',
        'messages' => [
          [
            "role" => "user",
            "content" => $account->openai_request . ': ' . $data,
          ],
        ],
        // 'temperature' => 1.0,
        // 'max_tokens' => 4000,
        // 'frequency_penalty' => 0,
        // 'presence_penalty' => 0,
      ]);
  
      $d = json_decode($chat);
      return $d->choices[0]->message->content;
    }
  
    // Выводит запись на страницу - получает подробное описание;
    public function getDescription()
    {
        $account = Account::find()
        ->where(['user_id' => Yii::$app->user->identity->id])
        ->one();

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

                        // Сохраняю данные в таблицу Summary;
                        $chunksList = $arr_body->response->chunks;
                        $item->detail = $arr_body->response->chunks[0]->alternatives[0]->text;
                        $item->updated_at = $this->getCurrentDate();
                        $item->summary_status = 2; // Получение краткого описания;
                        $item->summary = $this->getSummary($chunksList[0]->alternatives[0]->text, $account);  // Получаю один из вариантов расшифровки текста;
                    
                        // Сохраняю данные в таблицу Detail;
                        $newDetail = new Detail;
                        $newDetail->summary_id = $item->id;
                        $newDetail->detail_text = $arr_body->response->chunks[0]->alternatives[0]->text; // $chunkItem->alternatives[0]->text;

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
                        $item->summary_status = 4;
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
        }
    }

    // Создает новую запись;
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
        $newItem->summary_status = 1; // Конвертация речи в текст;
      } else {
        $newItem->summary_status = 2; // Получение краткого описания;
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

    // Редактирует данные для доступа к Яндекс Storage и Chat GPT;
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
      $editAccount->openai_api_key = $accountFormModel->openai_api_key;
      $editAccount->openai_chat_model = $accountFormModel->openai_chat_model;
      $editAccount->openai_request = $accountFormModel->openai_request;

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
}
