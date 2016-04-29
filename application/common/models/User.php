<?php
namespace common\models;

use yii\base\Component;
use yii\web\IdentityInterface;

class User extends Component implements IdentityInterface
{
    var $client_id = null;
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
            $configs=[];
            $configs["client_id"] = $client['id'];
            return new User($configs);
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

    public function getClientId()
    {
        return $this->client_id;
    }
}