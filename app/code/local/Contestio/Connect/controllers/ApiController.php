<?php
class Contestio_Connect_ApiController extends Mage_Core_Controller_Front_Action
{
    // public function preDispatch()
    // {
    //     parent::preDispatch();
        
    //     $response = $this->getResponse();
    //     $origin = $this->getRequest()->getHeader('Origin');
        
    //     if ($origin === 'http://127.0.0.1:3001' || $origin === 'http://localhost:3001') {
    //         $response->setHeader('Access-Control-Allow-Origin', $origin);
    //         $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH');
    //         $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, clientkey, clientsecret, externalId, cache-control, pragma, expires');
    //         $response->setHeader('Access-Control-Allow-Credentials', 'true');
    //         $response->setHeader('Access-Control-Max-Age', '86400');
    //     }

    //     if ($this->getRequest()->getMethod() == 'OPTIONS') {
    //         $response->setHttpResponseCode(200)->sendResponse();
    //         exit;
    //     }
    // }
    public function proxyAction()
    {
        $helper = Mage::helper('contestio_connect/api');

        $apiKey = Mage::getStoreConfig('contestio_connect/api_settings/api_key');
        $apiSecret = Mage::getStoreConfig('contestio_connect/api_settings/api_secret');

        $endpoint = $this->getRequest()->getParam('endpoint');
        $method = $this->getRequest()->getMethod();
        $data = json_decode($this->getRequest()->getRawBody(), true);
        $userAgent = $this->getRequest()->getHeader('User-Agent');

        // Get mobile or desktop with userAgent
        $isImageRequest = isset($_FILES['file']) && !empty($_FILES['file']['tmp_name']);

        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId() ?? null;

        // if $endpoint is `me`, return the customer data
        if ($endpoint === 'me') {
            $response = $this->getMe();
            $this->sendJsonResponse($response, 200);
            return;
        } else if ($endpoint === 'pseudo' && $method === 'POST') {
            $response = $this->handlePseudoUpdate($data);
            if (!$response['success']) {
                $this->sendJsonResponse($response, 401);
                return;
            }
            $endpoint = 'v1/users/final/upsert';
            $data = $response['data'];
        }

        try {
            if ($isImageRequest) {
                // Traitement spécial pour les requêtes d'image
                $response = $this->handleImageUpload($userAgent, $endpoint);
            } else {
                $response = $helper->callApi($userAgent, $endpoint, $method, $data);
            }

            $this->sendJsonResponse($response, 200);
        } catch (Exception $e) {
            // Decode error message
            $error = json_decode($e->getMessage(), true);
            $this->sendJsonResponse($error, $e->getCode() > 0 ? $e->getCode() : 500);
        }
    }

    private function sendJsonResponse($data, $statusCode)
    {
        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($data))
            ->setHttpResponseCode($statusCode);
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
            'createdAt' => $customer->getCreatedAt(),
        ];

        return $response;
    }

    private function handlePseudoUpdate($data)
    {
        $userData = $this->getMe();
        if (!$userData) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour modifier votre pseudo.'];
        }

        return [
            'success' => true,
            'data' => [
                'externalId' => $userData['id'],
                'email' => $userData['email'],
                'fname' => $userData['firstName'],
                'lname' => $userData['lastName'],
                'pseudo' => $data['pseudo'],
                'isFromContestio' => $data['isFromContestio'],
                'createdAt' => $userData['createdAt'],
                'currentTimestamp' => time(),
            ]
        ];
    }

    private function handleImageUpload($userAgent, $endpoint)
    {
        if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
            throw new Exception("Aucun fichier n'a été téléchargé");
        }

        $file = $_FILES['file'];
        $helper = Mage::helper('contestio_connect/api');

        try {
            $response = $helper->uploadImage($userAgent, $endpoint, $file);
            return $response;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du téléchargement de l'image : " . $e->getMessage());
        }
    }
}
