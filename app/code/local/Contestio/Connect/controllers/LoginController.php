<?php

class Contestio_Connect_LoginController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    // public function preDispatch()
    // {
    //     parent::preDispatch();
        
    //     $response = $this->getResponse();
    //     $origin = $this->getRequest()->getHeader('Origin');
        
    //     if ($origin === 'http://127.0.0.1:3001' || $origin === 'http://localhost:3001') {
    //         $response->setHeader('Access-Control-Allow-Origin', $origin);
    //         $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
    //         $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, clientkey, clientsecret, externalId, cache-control, pragma, expires');
    //         $response->setHeader('Access-Control-Allow-Credentials', 'true');
    //         $response->setHeader('Access-Control-Max-Age', '86400');
    //     }

    //     if ($this->getRequest()->getMethod() == 'OPTIONS') {
    //         $response->setHttpResponseCode(200)->sendResponse();
    //         exit;
    //     }
    // }
    public function postAction()
    {
        $content = json_decode($this->getRequest()->getRawBody(), true);
        $username = $content['username'] ?? null;
        $password = $content['password'] ?? null;

        if ($username && $password) {
            try {
                $session = Mage::getSingleton('customer/session');
                $session->login($username, $password);
                if ($session->isLoggedIn()) {
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Veuillez fournir un identifiant et un mot de passe.'];
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}