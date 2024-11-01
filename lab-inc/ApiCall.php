<?php

namespace AmazonPay;

require_once 'AmazonPay/Client.php';
require_once 'AmazonPay/ResponseParser.php';
require_once 'AmazonPay/Signature.php';
if (!defined('ABSPATH')) exit;
class ApiCall
{
    public function AuthorizeCapture($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->authorize($apiCallParams);
    }

    public function Refund($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->refund($apiCallParams);
    }

    public function getBillingAgreementDetails($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->getBillingAgreementDetails($apiCallParams);
    }

    public function setBillingAgreementDetails($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->setBillingAgreementDetails($apiCallParams);
    }

    public function confirmBillingAgreement($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->confirmBillingAgreement($apiCallParams);
    }
    public function validateBillingAgreement($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->validateBillingAgreement($apiCallParams);
    }
    public function authorizeOnBillingAgreement($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->authorizeOnBillingAgreement($apiCallParams);
    }
    public function getAuthorizationDetails($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->getAuthorizationDetails($apiCallParams);
    }
    public function getCaptureDetails($apiCallParams,$configParams)
    {
        $client = new Client($configParams);
        return $client->getCaptureDetails($apiCallParams);
    }

    public function GetArrayResponse($response)
    {
        $responseObj = new ResponseParser($response);
        return $arrayResponse = $responseObj->toArray();
    }
    public function getSignature($configParams, $apiCallParams)
    {
        $client = new Signature($configParams,$apiCallParams);
        return $client->getSignature();
    }

}