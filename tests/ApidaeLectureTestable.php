<?php

namespace PierreGranger\Tests;

use GuzzleHttp\Client;
use PierreGranger\ApidaeLecture;

/**
 * Sous-classe de test permettant d'injecter un client Guzzle mocké.
 */
class ApidaeLectureTestable extends ApidaeLecture
{
    public function __construct(?array $params, Client $client)
    {
        parent::__construct($params);
        $this->client = $client;
    }
}
