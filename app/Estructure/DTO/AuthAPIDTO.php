<?php

/**
 * Clase perteneciente a la capa de transporte de datos (Data Transfer Object) para la comunicacion entre las capas BLL y DAO, que contiene
 * los atributos necesarios para este proposito con respecto a autenticacion de usuarios para la utilizacion de la API.
 *
 * @category  API
 * @package   API/DTO
 * @example 
 * @version   0.01
 * @since     2016-04-25
 * @author hherrera
 */
class AuthAPIDTO {
    
    private $user;
    private $pass;
    private $passHash;
    private $ip;
    private $apiKey;
    private $state;
    
    function __construct($user = NULL, $pass = NULL, $passHash = NULL, $ip = NULL, $apiKey = NULL, $state = NULL) {
        $this->user = $user;
        $this->pass = $pass;
        $this->passHash = $passHash;
        $this->ip = $ip;
        $this->apiKey = $apiKey;
        $this->state = $state;
    }
    
    function getUser() {
        return $this->user;
    }

    function getPass() {
        return $this->pass;
    }

    function getPassHash() {
        return $this->passHash;
    }

    function getIp() {
        return $this->ip;
    }

    function getApiKey() {
        return $this->apiKey;
    }

    function setUser($user) {
        $this->user = $user;
    }

    function setPass($pass) {
        $this->pass = $pass;
    }

    function setPassHash($passHash) {
        $this->passHash = $passHash;
    }

    function setIp($ip) {
        $this->ip = $ip;
    }

    function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
    }

    function getState() {
        return $this->state;
    }

    function setState($state) {
        $this->state = $state;
    }

}
