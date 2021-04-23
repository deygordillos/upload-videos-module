<?php
namespace App\Estructure\BLL;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Description of Mailer
 *
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 */
class MailerBLL extends PHPMailer {

    private $error;

    public function __construct() {
        parent::__construct(true);        
        $this->setFrom(MAIL_FROM, MAIL_FROM_NAME);        
        $this->isHTML(true);
        // Activo condificacción utf-8
        $this->CharSet = 'UTF-8';
    }

    /**
     * [__destruct Destruir la clase y la del modelo]
     */
    public function __destruct() {
        parent::__destruct();
    }

    public function setError($e) {
        $this->error = $e;
    }

    public function getError() {
        return $this->error;
    }

}
