<?php
/**
 * Created by PhpStorm.
 * User: banban
 * Date: 17/12/17
 * Time: 20:18
 */

namespace AppBundle\Service;

use AppBundle\AppBundle;
use AppBundle\Entity\User;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class NotificationService
{

    private $db;


    public function __construct(RegistryInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @return mixed
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @param mixed $db
     * @return NotificationService
     */
    public function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    public function sendNotif($mail, EmailService $emailService)
    {

    }


}