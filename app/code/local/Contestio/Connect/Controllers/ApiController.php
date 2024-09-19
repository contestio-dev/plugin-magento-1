<?php
class Contestio_Connect_ApiController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        parent::preDispatch();
        
        $response = $this->getResponse();
        $origin = $this->getRequest()->getHeader('Origin');
        
        if ($origin === 'http://127.0.0.1:3001' || $origin === 'http://localhost:3001') {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, clientkey, clientsecret, externalId');
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
            $response->setHeader('Access-Control-Max-Age', '86400');
        }

        if ($this->getRequest()->getMethod() == 'OPTIONS') {
            $response->setHttpResponseCode(200)->sendResponse();
            exit;
        }
    }
    public function proxyAction()
    {
        $helper = Mage::helper('contestio_connect/api');

        $apiKey = Mage::getStoreConfig('contestio_connect/api_settings/api_key');
        $apiSecret = Mage::getStoreConfig('contestio_connect/api_settings/api_secret');

        $endpoint = $this->getRequest()->getParam('endpoint');
        $method = $this->getRequest()->getMethod();
        $data = json_decode($this->getRequest()->getRawBody(), true);

        // $url = "https://api.contestio.fr/" . $endpoint;
        $url = "http://host.docker.internal:3000/" . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'clientkey: ' . $apiKey,
            'clientsecret: ' . $apiSecret,
            'externalId: 2'
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody($response)
            ->setHttpResponseCode($httpCode);
    }
}