<?php

class Contestio_Connect_Helper_Api extends Mage_Core_Helper_Abstract
{
    private $apiKey;
    private $apiSecret;
    private $customerId;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = Mage::getStoreConfig('contestio_connect/api_settings/api_key');
        $this->apiSecret = Mage::getStoreConfig('contestio_connect/api_settings/api_secret');
        $this->customerId = Mage::getSingleton('customer/session')->getCustomer()->getId() ?? null;
    }


    private function getApiBaseUrl()
    {
        $baseUrl = Mage::getStoreConfig('contestio_connect/api_settings_advanced/base_url');
        return $baseUrl ? $baseUrl : 'https://api.contestio.fr';
    }

    private function getHeaders($contentType = 'application/json')
    {
        return [
            'Content-Type: ' . $contentType,
            'clientkey: ' . $this->apiKey,
            'clientsecret: ' . $this->apiSecret
        ];
    }

    public function callApi($userAgent, $endpoint, $method, $data = null, $externalId = null)
    {
        try {
            $ch = curl_init($this->getApiBaseUrl() . '/' . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout après 5 secondes
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Timeout de connexion après 3 secondes
            
            $headers = $this->getHeaders();
            $headers[] = 'clientuseragent: ' . $userAgent;

            // If external id is provided, use it (for order observer)
            if ($externalId) {
                $headers[] = 'externalid: ' . $externalId;
            } else if ($this->customerId) {
                // Else, get user id from session
                $headers[] = 'externalid: ' . $this->customerId;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("Erreur CURL: " . $error);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                if ($endpoint === 'v1/users/final/generate-token') {
                    $response = json_decode($response, true);
                    // Add apiurl to response
                    $response['apiurl'] = $this->getApiBaseUrl();

                    return $response;
                }

                // Else, return json decoded response
                return json_decode($response, true);
            }

            
            throw new Exception($response, $httpCode);
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function uploadImage($userAgent, $endpoint, $file)
    {
        $ch = curl_init($this->getApiBaseUrl() . '/' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        $headers = $this->getHeaders('multipart/form-data');
        $headers[] = 'clientuseragent: ' . $userAgent;
        if ($this->customerId) {
            $headers[] = 'externalid: ' . $this->customerId;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $postFields = [
            'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            if (strpos($contentType, 'image/webp') !== false) {
                return base64_encode($response);
            }
            return json_decode($response, true);
        } else {
            return $response;
            throw new Exception($response, $httpCode);
        }
    }
}
