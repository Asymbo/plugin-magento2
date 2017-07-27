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

class Address 
{
    private $customer;
    
    public function __construct(\Magento\Customer\Model\Customer $customer)
    {
        $this->customer = $customer;
    }
    
    public function getAddress($data, $responseData)
    { 
        $this->customer->load($data->external_id);
        if($this->customer->getId() && $this->customer->getEmail() == $data->username) {
            $addresses = array();
            foreach ($this->customer->getAddresses() as $address) {
                $address['email'] = $this->customer->getEmail();
                $addresses['addresses'][] = $this->createAddress($address->toArray()); 
            }
            $responseData->data = array('data' => $addresses);
        } else {
            $responseData->success = false;
            $responseData->http_code = 404;
            $responseData->data = array('data' => null);
        }
        
        return $responseData;
    }
    
    protected function createAddress($address)
    {
        $return = array(
            'external_id'   =>  $address['entity_id'],
            'name'          =>  $address['firstname'] . (isset($address['middlename']) ? " " . $address['middlename'] : ""),
            'surname'       =>  $address['lastname'],
            'street'        =>  $address['street'],
            'postal'        =>  $address['postcode'],
            'city'          =>  $address['city'],
            'phone'         =>  $address['telephone'],
            'email'         =>  $address['email'],
            'company'       =>  $address['company']
        );
            
        return $return;
    }
    
    
}
