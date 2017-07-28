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
namespace Asymbo\Plugin\Model\Webhook;

class Order 
{
    private $debug;
    
    private $productFactory;
    private $store;
    private $customerFactory;
    private $quote;
    private $quoteManagement;
    private $orderService;
    private $customerRepository;
    private $subscriber;
    private $emailSender;
    private $defaultCountry;
    private $scopeConfig;
    private $defaultRegion;
    private $countryFactory;
    
    public function __construct(
            \Magento\Catalog\Model\ProductFactory $productFactory,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Magento\Customer\Model\CustomerFactory $customerFactory,
            \Magento\Quote\Model\Quote $quote,
            \Magento\Quote\Model\QuoteManagement $quoteManagement,
            \Magento\Sales\Model\Service\OrderService $orderService,
            \Magento\Customer\Model\ResourceModel\CustomerRepository $customerRepository,
            \Magento\Newsletter\Model\Subscriber $subscriber,
            \Magento\Sales\Model\AdminOrder\EmailSender $emailSender,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Directory\Model\CountryFactory $countryFactory
            )
    {
        $this->countryFactory = $countryFactory;
        $this->scopeConfig = $scopeConfig;
        $this->defaultCountry = $scopeConfig->getValue('general/country/default');
        $this->defaultRegion = $this->setDefaultRegionId();
        $this->productFactory = $productFactory;
        $this->store = $storeManager->getDefaultStoreView();
        $this->customerFactory = $customerFactory;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->orderService = $orderService;
        $this->customerRepository = $customerRepository;
        $this->subscriber = $subscriber;
        $this->emailSender = $emailSender;
    }
    
    
    public function saveOrder($data, $responseData, $debug)
    {         
        $this->debug = $debug;
        
        if(!empty($data->user->external_id)) {
            $customer = $this->getCustomer($data);
            if($customer !== null) {
                $customerResource = $this->customerRepository->getById($customer->getId());
            }
        } else {
            $customer = null;
        }
        
        try {
            $quoteId = $this->quoteManagement->createEmptyCart();
            $quote = $this->quote->loadActive($quoteId);
            
            if($customer !== null) {
                $quote->assignCustomer($customerResource);
            } else {
                $quote->setCustomerIsGuest(true);
                $quote->setCustomerEmail($data->payment_address->email);
            }
            
            $quote->setStore($this->store);
            $quote->setCurrency();

            // Configure Notification
            $quote->setSendCconfirmation(1);
            $this->setProducts($data, $quote);

            $this->setShippingAndPayment($data, $quote, $customer);

            // Collect Totals
            $quote->collectTotals()->save();

            //modify product prices by prices from request
            $this->modifyPruductsPrices($data, $quote);

            // Create Order From Quote
            $order = $this->quoteManagement->submit($quote);

            $this->modifyOrderPrices($data, $order);
            $orderId = $order->getRealOrderId();
            
            $this->emailSender->send($order);
            
        } catch (\Exception $ex) {  
            if ($this->debug) {
                throw $ex;
            } else {    
                $orderId = null;
                $responseData->success = false;
                $responseData->http_code = 400;
            }
        }
        
        unset($quote);

        $responseData->data = array(
            'data' => array(
                'external_id' => $orderId
            ),
        );
         
        return $responseData;
    }
    
    
    protected function modifyOrderPrices($data, $order)
    {
        $order->setData('subtotal', $data->price_products_vat);
        $order->setData('base_subtotal', $data->price_products_vat);
         
        $order->setData('subtotal_with_discount', $data->price_products_vat);
        $order->setData('base_subtotal_with_discount', $data->price_products_vat);
        $order->setData('base_subtotal_incl_tax', $data->price_products_vat);
        $order->setData('subtotal_incl_tax', $data->price_products_vat);
        $order->setData('grand_total', $data->price_total_vat);
        $order->setData('base_grand_total', $data->price_total_vat);
        $order->setData('base_tax_amount', 0);
        $order->setData('tax_amount', 0);
        //order note
        if(isset($data->note)) {
            $order->addStatusHistoryComment($data->note);
        }
        //total discounts
        $discountAmount = 0;
        foreach($data->discounts as $discount) {
            $discountAmount = $discountAmount + $discount->price_vat;
        }
        $order->setData('discount_amount', $discountAmount);
        //total shipping
        if(isset($data->delivery_type->price_vat)) {
            $order->setData('shipping_amount', $data->delivery_type->price_vat);
            $order->setData('shipping_incl_tax', $data->delivery_type->price_vat);
            $order->setData('base_shipping_incl_tax', $data->delivery_type->price_vat);
        }
        $order->setData('shipping_description', $data->delivery_type->name);
        
        if(isset($data->delivery_type->branch_address)) {
            $shippingDescription = $order->getData('shipping_description');
            $shippingDescription .= " - " . $data->delivery_type->branch_address->name . ", ";
            $shippingDescription .= $data->delivery_type->branch_address->street . ", ";
            $shippingDescription .= $data->delivery_type->branch_address->postal . ", ";
            $shippingDescription .= $data->delivery_type->branch_address->city;
            $order->setData('shipping_description', $shippingDescription);
        }
        
        $order->save();
    }
    
    protected function modifyPruductsPrices($data, $quote)
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            foreach($data->products as $product) {
                $idProduct = $product->external_id; 
                if($idProduct == $item->getData('product_id')) {

                    $item->setData('name', $product->name);
                    $item->setData('price', $product->price_vat);
                    $item->setData('calculation_price', $product->price_vat);
                    $item->setData('converted_price', $product->price_vat);
                    $item->setData('price_incl_tax', $product->price_vat);
                    $item->setData('base_price_incl_tax', $product->price_vat);
                    $item->setData('base_price', $product->price_vat);
                    $item->setData('base_original_price', $product->price_vat);
                    $item->setData('row_total_incl_tax', $product->price_vat * $product->count);
                    $item->setData('base_row_total', $product->price_vat * $product->count);
                    $item->setData('row_total', $product->price_vat * $product->count);
                    $item->setData('discount_calculation_price', $product->price_vat);
                    $item->setData('base_discount_calculation_price', $product->price_vat);
                    $item->setData('base_row_total_incl_tax', $product->price_vat * $product->count);
                    

                    $item->setData('quote_item_price', $product->price_vat);
                    $item->setData('quote_item_row_total', $product->price_vat * $product->count);
                    
                    $item->setData('tax_amount', 0);
                    $item->setData('tax_percent', 0);
                    $item->setData('base_tax_amount', 0);
                }
            }
        }
    }
    
    protected function setShippingAndPayment($data, $quote, $customer = null)
    {
        // Set Sales Order Billing Address
        $this->setBilling($data, $quote, $customer);
       
        // Set Sales Order Shipping Address
        $shippingAddress = $this->setShipping($data, $quote, $customer);

        //$allActiveShippingMethods = Mage::getSingleton('shipping/config')->getActiveCarriers();
        //$allActivePaymentMethods = Mage::getModel('payment/config')->getActiveMethods();
       
         // Collect Rates and Set Shipping & Payment Method
        $shippingMethod = $data->delivery_type->external_id;
        $paymentMethod = $data->payment_type->external_id;
        $quote->getShippingAddress();
        $quote->collectTotals(); 
        $shippingAddress->setCollectShippingRates(true)
                        ->collectShippingRates()
                        ->setShippingMethod($shippingMethod)
                        ->setPaymentMethod($paymentMethod);      
        $quote->save();
        $quote->getPayment()->importData(array('method' => $paymentMethod));

    }
    
    
    protected function getCustomer($data)
    {
        $customer = $this->customerFactory->create();
        //$customer->setWebsiteId(1);//->setStore($this->store);
        if(!empty($data->user->external_id)) {
            $customer->load($data->user->external_id);
        } 
        
        if($customer->getId() && $customer->getEmail() == $data->user->username) {
            if($data->agree_promo == 1) {
                $this->subscribeNewsletter($customer);
            } 
        } else {
            $customer = null;
        }
        
        return $customer;
    }
    
    protected function subscribeNewsletter($customer)
    {
        $subscriber = $this->subscriber->loadByCustomerId($customer->getId());

        if (!$subscriber->getId() 
                || $subscriber->getStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_UNSUBSCRIBED
                || $subscriber->getStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_NOT_ACTIVE) 
        {
            $subscriber->setStatus(\Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);
            $subscriber->setSubscriberEmail($customer->getEmail());
            $subscriber->setSubscriberConfirmCode($subscriber->RandomSequence());
        }
        $subscriber->setStoreId($this->store->getId());
        $subscriber->setCustomerId($customer->getId());
        $subscriber->save();
    }
    
    
    protected function setProducts($data, $quote) 
    {
        foreach($data->products as $product) {
            $productObject = $this->productFactory->create();
            $productObject->load($product->external_id);
            if($productObject->getId() === null) {
                throw new Exception("Product " . $product->external_id . " doesn't exist.");
            }
            
            $params = array(
                'product' => $product->external_id,
                'qty' => $product->count
            );
            
            //set choosen variant
            if($productObject->getTypeId() == 'configurable' && !empty($product->variant_id)) {
                $childObject = $this->productFactory->create();
                $childObject->load($product->variant_id);
                if($childObject->getId() === null) {
                    throw new Exception("Variant " . $product->variant_id . " doesn't exist.");
                }
                $childObjectArr = $childObject->toArray();
                $productAttributeOptions = $productObject->getTypeInstance(true)->getConfigurableAttributesAsArray($productObject);

                foreach($productAttributeOptions as $super) {
                    $super_attribute[$super['attribute_id']] = $childObjectArr[$super['attribute_code']];
                }
                $params['super_attribute'] = $super_attribute; 
            }

            $quote->addProduct($productObject,new \Magento\Framework\DataObject($params));
        }
    }
    
    protected function setShipping($data, $quote, $customer = null)
    {
        $addressPreview = null;
        if(isset($data->delivery_address)) {
            $address = $data->delivery_address;
        } else {
            $address = $data->payment_address;
        }
        if($customer !== null && !empty($customer->getAddresses())) {
            foreach ($customer->getAddresses() as $custAddress) {
                $addressPreview = $custAddress;
                break;
            }
        }

        if($addressPreview === null || $customer === null) {
            $shippingAddress = $quote->getShippingAddress()->addData(array(
                'prefix' => '',
                'firstname' => $address->name,
                'middlename' => '',
                'lastname' => $address->surname,
                'email' => $address->email,
                'suffix' => '',
                'company' => isset($address->company) ? $address->company : "", 
                'street' => $address->street,
                'city' => $address->city,
                'country_id' => $this->defaultCountry,
                'region' => $this->defaultRegion,
                'postcode' => $address->postal,
                'telephone' => $address->phone,
                'vat_id' => '',
                'save_in_address_book' => 0
            ));
        } else {
            $addressDef = $addressPreview->toArray();
            $shippingAddress = $quote->getShippingAddress()->addData(array(
                'customer_address_id' => $address->id,
                'firstname' => $address->name,
                'lastname' => $address->surname,
                'email' => $address->email,
                'company' => $address->company, 
                'street' => $address->street,
                'city' => $address->city,
                'country_id' => $addressDef['country_id'],
                'region' => $addressDef['region'],
                'postcode' => $address->postal,
                'telephone' => $address->phone,
                'save_in_address_book' => 0
            ));
        }
        
        return $shippingAddress;
    }
    
    protected function setBilling($data, $quote, $customer = null)
    {
        $addressPreview = null;
        if($customer !== null && !empty($customer->getAddresses())) {
            foreach ($customer->getAddresses() as $custAddress) {
                $addressPreview = $custAddress;
                break;
            }
        }
        
        if($addressPreview === null || $customer === null) {
            $billingAddress = $quote->getBillingAddress()->addData(array(
                'prefix' => '',
                'firstname' => $data->payment_address->name,
                'middlename' => '',
                'lastname' => $data->payment_address->surname,
                'suffix' => '',
                'email' => $data->payment_address->email,
                'company' => $data->payment_address->company, 
                'street' => $data->payment_address->street,
                'city' => $data->payment_address->city,
                'country_id' => $this->defaultCountry,
                'region' => $this->defaultRegion,
                'postcode' => $data->payment_address->postal,
                'telephone' => $data->payment_address->phone,
                'vat_id' => '',
                'save_in_address_book' => 0
            ));
        } else {
            $address = $addressPreview->toArray();
            $billingAddress = $quote->getBillingAddress()->addData(array(
                'customer_address_id' => $data->payment_address->id,
                'firstname' => $data->payment_address->name,
                'lastname' => $data->payment_address->surname,
                'email' => $data->payment_address->email,
                'company' => $data->payment_address->company, 
                'street' => $data->payment_address->street,
                'city' => $data->payment_address->city,
                'country_id' => $address['country_id'],
                'region' => $address['region'],
                'postcode' => $data->payment_address->postal,
                'telephone' => $data->payment_address->phone,
                'save_in_address_book' => 0
            ));
        }
        return $billingAddress;
    }
    
    protected function setDefaultRegionId()
    {

        $regionId = null;
        $requireRegion = $this->scopeConfig->getValue('general/region/state_required');
        $requireRegion = explode(',', $requireRegion);
        if(in_array($this->defaultCountry, $requireRegion )) {
            $regionsCollection = $this->countryFactory->create()->setId($this->defaultCountry)->getLoadedRegionCollection()->toArray();
            foreach($regionsCollection['items'] as $reg) {
                $regionId = $reg['region_id'];
                break;
            }
        }
        
        return $regionId;
        
    }
}
