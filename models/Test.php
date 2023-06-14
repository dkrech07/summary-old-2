<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "test".
 *
 * @property string $text
 * @property string $date_at
 */
class Test extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'test';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['text', 'date_at'], 'required'],
            [['text', 'date_at'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'text' => 'Text',
            'date_at' => 'Date At',
        ];
    }
}
