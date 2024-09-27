<?php

class Contestio_Connect_LoginController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

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
    public function postAction()
    {
        $username = $this->getRequest()->getPost('username');
        $password = $this->getRequest()->getPost('password');

        if ($username && $password) {
            try {
                $session = Mage::getSingleton('customer/session');
                $session->login($username, $password);
                if ($session->isLoggedIn()) {
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'message' => 'Invalid login or password.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Please provide both username and password.'];
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}