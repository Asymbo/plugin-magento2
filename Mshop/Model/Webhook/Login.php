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
namespace Asymbo\Mshop\Model\Webhook;


class Login
{
    private $customerAccountManagement;
    
    public function __construct(\Magento\Customer\Model\AccountManagement $customerAccountManagement)
    {
        $this->customerAccountManagement = $customerAccountManagement;
    }
    
    public function checkUser($data, $responseData) 
    {        
        try {
            $customer = $this->customerAccountManagement->authenticate($data->username, $data->password);
            if ($customer) {
               $responseData->data = $this->loginSuccess($customer);
            }
        } catch (\Exception $e) {
            $responseData->success = false;
            $responseData->http_code = 403;
            $responseData->data = $this->loginFailed();
        }
        return $responseData;
    }
    
    
    protected function loginSuccess($customer)
    {
        $json = array(
            'data' => array(
                'username' => $customer->getEmail(),
                'email' => $customer->getEmail(),
                'external_id' => $customer->getId(),
                "price_group" => $customer->getGroupId()
            ),
        );
        
        return $json;
    }
    
    protected function loginFailed()
    {
        $json = array(
            'data' => array(
                'username' => null,
                'email' => null,
                'external_id' => null,
                "price_group" => null
            ),
        );
        
        return $json;
    }
}
