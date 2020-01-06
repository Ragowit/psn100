<?php
class Database extends PDO
{
    private $host;
    private $database;
    private $user;
    private $password;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->host = "";
        $this->database = "";
        $this->user = "";
        $this->password = "";

        $dns = "mysql:host=" . $this->host . ";dbname=" . $this->database . ";charset=utf8mb4";

        try {
            parent::__construct($dns, $this->user, $this->password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => false));
        } catch (PDOException $e) {
            echo $e->getMessage();
            die();
        }
    }
}
