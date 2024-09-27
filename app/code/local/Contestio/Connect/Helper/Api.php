<?php

class Contestio_Connect_Helper_Api extends Mage_Core_Helper_Abstract
{
    // const API_URL = 'http://127.0.0.1:3000/v1';
    const API_URL = 'http://host.docker.internal:3000/v1';

    public function callApi($endpoint, $method = 'GET', $data = [])
    {
        // Add log
        Mage::log('Calling API: ' . $endpoint, null, 'contestio_api.log');
        // exit;
        // $apiKey = Mage::getStoreConfig('contestio_connect/api_settings/api_key');
        // $apiSecret = Mage::getStoreConfig('contestio_connect/api_settings/api_secret');

        // $curl = curl_init();

        // $url = self::API_URL . $endpoint;

        // $headers = [
        //     'Content-Type: application/json',
        //     'clientkey: ' . $apiKey,
        //     'clientsecret: ' . $apiSecret
        // ];

        // curl_setopt_array($curl, [
        //     CURLOPT_URL => $url,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => '',
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 30,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_CUSTOMREQUEST => $method,
        //     CURLOPT_HTTPHEADER => $headers,
        // ]);

        // if ($method === 'POST' && !empty($data)) {
        //     curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        // }

        // $response = curl_exec($curl);
        // $err = curl_error($curl);

        // curl_close($curl);

        // if ($err) {
        //     Mage::log('Erreur cURL : ' . $err, null, 'contestio_api.log');
        //     return false;
        // }

        // return json_decode($response, true);
    }
}