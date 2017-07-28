<?php
/**
 * NOTICE OF LICENSE.
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *  @author    Asymbo s.r.o.
 *  @copyright 2014-2016 Asymbo s.r.o.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
namespace Asymbo\Plugin\Controller\Webhook;

use \Magento\Framework\App\Action\Action;


class Actions extends Action
{    
    private $debug = false;
    
    public function execute()
    {
        $this->setInitOptions();
        $this->checkAccess();
        if(!empty(file_get_contents('php://input'))) {
            $data = json_decode(file_get_contents('php://input'));
        } else {
            $data = null;
        }
        if (json_last_error() === 0) {
            $do = current(array_keys($this->getRequest()->getParams()));
            switch($do) {
                case 'login'            : return $this->login($data);
                case 'get-address'      : return $this->getAddress($data);
                case 'get-stock-count'  : return $this->getStockCount($data);
                case 'save-order'       : return $this->saveOrder($data);
                case 'get-info'         : return $this->getInfo();
                default: null;
            } 
        } else {
            $response = $this->prepareResponseData();
            $response->http_code = 400;
            return $this->setOutput($response);
        }          
    }
    
    public function login($data) 
    {
        $login = $this->_objectManager->get('\Asymbo\Plugin\Model\Webhook\Login');
        $response = $login->checkUser($data, $this->prepareResponseData());
        return $this->setOutput($response);
    }
    
    public function getAddress($data) 
    {
        $address = $this->_objectManager->get('\Asymbo\Plugin\Model\Webhook\Address');
        $response = $address->getAddress($data, $this->prepareResponseData());
        return $this->setOutput($response);
    }
    
    public function getStockCount($data) 
    {
        $address = $this->_objectManager->get('\Asymbo\Plugin\Model\Webhook\StockCount');
        $response = $address->getStockCount($data, $this->prepareResponseData());
        return $this->setOutput($response);
    }
    
    public function saveOrder($data) 
    {
        $address = $this->_objectManager->get('\Asymbo\Plugin\Model\Webhook\Order');
        $response = $address->saveOrder($data, $this->prepareResponseData(), $this->debug);
        return $this->setOutput($response);
    }
    
    public function getInfo() 
    {
        $getInfo = $this->_objectManager->get('\Asymbo\Plugin\Model\Webhook\GetInfo');
        $response = $getInfo->getInfo($this->prepareResponseData());
        return $this->setOutput($response);
    }
    
    protected function setOutput($responseData)
    {
        $jsonResultFactory = $this->_objectManager->get('\Magento\Framework\Controller\Result\JsonFactory');
        $result = $jsonResultFactory->create();

        $result->setHttpResponseCode($responseData->http_code);
        $result->setData($responseData->data);
        return $result;
    }
    
    protected function prepareResponseData()
    {
        return (Object)Array('data' => array(), 'success' => true, 'http_code' => 200);
    }
    
    protected function checkAccess()
    { 
        $success = false;
        $data = $this->getAutorizationData();
        
        if(!empty($data)) {
            $payload = file_get_contents('php://input');
            $message = sprintf('@%s@%s@%s@', $data['timestamp'], $data['requestUri'], $payload);
            $verifyAuthToken = hash_hmac('sha256', $message, $data['secretKey']);
            if ($data['authToken'] === $verifyAuthToken) {
                $success = true;
            }  
        }
        
        if(!$success) {
            http_response_code(401);
            header('Powered-By: Magento');
            header("Content-type: text/plain");
            exit();
        }
    }
    
    protected function getAutorizationData()
    {
        $return = array();
        if(isset($_SERVER['HTTP_X_ASYMBO_TIMESTAMP']) && isset($_SERVER['HTTP_X_ASYMBO_AUTH_TOKEN'])) {
            $return['timestamp'] = $_SERVER['HTTP_X_ASYMBO_TIMESTAMP'];
            $protocol = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS']) ? 'https' : 'http';
            $return['requestUri'] = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $return['authToken'] = $_SERVER['HTTP_X_ASYMBO_AUTH_TOKEN'];
            
            $scopeConfig = $this->_objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
            $return['secretKey'] = $scopeConfig->getValue('asymboshop/settings/secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            
        }
        return $return;
    }
  
    
    protected function setInitOptions()
    {
        if(isset($_GET['debug'])) {
            $this->debug = true;
        }

        if(function_exists('error_reporting')) {
            if($this->debug) {
                error_reporting(E_ALL);
            } else {
                error_reporting(0);
            }
        }
        if(function_exists('set_time_limit')) {
            set_time_limit(200);
        }
    }
}