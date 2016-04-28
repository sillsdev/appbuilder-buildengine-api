<?php
namespace common\models;

use yii\base\Component;
use yii\web\IdentityInterface;

class User extends Component implements IdentityInterface
{
    public static function findIdentity($id)
    {
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        $validToken = getenv('API_ACCESS_TOKEN');
        if($token == $validToken){
            return new User();
        }
        $client = Client::findByAccessToken($token);
        if ($client) {
            return new User($client['prefix']);
        }

        return null;
    }

    public function getId()
    {
        return null;
    }

    public function getAuthKey()
    {
        return null;
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }
}