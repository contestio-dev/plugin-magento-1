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
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH');
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

        $isImageRequest = isset($_FILES['file']) && !empty($_FILES['file']['tmp_name']);

        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId() ?? null;

        // if $endpoint is `me`, return the customer data
        if ($endpoint === 'me') {
            $response = $this->getMe();
            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setBody(json_encode($response))
                ->setHttpResponseCode(200);
            return;
        } else if ($endpoint === 'pseudo' && $method === 'POST') {
            $userData = $this->getMe();

            // Check if user is logged in
            if (!$userData) {
                $response = ['success' => false, 'message' => 'Vous devez être connecté pour modifier votre pseudo.'];
                $this->getResponse()
                    ->setHeader('Content-type', 'application/json')
                    ->setBody(json_encode($response))
                    ->setHttpResponseCode(401);
                return;
            }

            $pseudo = $data['pseudo'];

            // Update endpoint
            $endpoint = 'v1/users/final/upsert';

            // New datas
            $data = [
                'externalId' => $userData['id'],
                'email' => $userData['email'],
                'fname' => $userData['firstName'],
                'lname' => $userData['lastName'],
                'pseudo' => $pseudo,
            ];
        }

        $url = "https://dev.api.contestio.fr/" . $endpoint;
        // $url = "http://host.docker.internal:3000/" . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            $isImageRequest
                ? 'Content-Type: multipart/form-data'
                : 'Content-Type: application/json',
            'clientkey: ' . $apiKey,
            'clientsecret: ' . $apiSecret,
            // 'externalId: 1',
            'externalId: ' . $customerId,
        ]);

        // Check if file is present
        if ($isImageRequest) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file' => new CURLFile($_FILES['file']['tmp_name'], $_FILES['file']['type'], $_FILES['file']['name'])
            ]);
        } else if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // Check if the response is an image
        if ($isImageRequest && $httpCode === 201 && strpos($contentType, 'image/webp') !== false) {
            $response = base64_encode($response);
        }

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody($response)
            ->setHttpResponseCode($httpCode);
    }

    private function getMe()
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId() ?? null;
        $customer = Mage::getModel('customer/customer')->load($customerId);

        // Add pseudo to customer array
        $customer = Mage::getModel('customer/customer')->load($customerId);

        $response = [
            'id' => $customerId,
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
        ];

        return $response;
    }
}