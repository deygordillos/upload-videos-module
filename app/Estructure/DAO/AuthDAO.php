<?php

namespace App\Estructure\DAO;

use App\Estructure\BLL\BaseMethod;
use App\Estructure\BLL\MailerBLL;
use App\Utils\SDGConnectPDO;

class AuthDAO extends BaseMethod{

    public $table = 'API_USERS';

    public function getUserByUsername($username = '') {
        $sdgpdo = new SDGConnectPDO(USER_DB, PASS_DB, SCHEMA_DB, HOST_DB, PORT_DB);
        $sdgpdo->setLog($this->log);
        $sdgpdo->setTx($this->tx);
        $sdgpdo->setQuery("SELECT `idUser`, `username`, `password`, `statusUser`,
        `createdAt`, `updatedAt`, `legacy`
        FROM `{$this->table}`   
        WHERE `username` = :username 
        AND   `statusUser` = '1' LIMIT 1;");
        $sdgpdo->setParams([
            ":username" => $username
        ]);
        $data = $sdgpdo->getRow();
        $this->set('error', $sdgpdo->get('error') );
        $this->set('errorDescription', $sdgpdo->get('errorDescription') );
        return $data;
    }

}

