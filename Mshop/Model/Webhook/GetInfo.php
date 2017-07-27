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

class GetInfo
{
    
    private $deliveryModelConfig;
    private $paymentModelConfig;
    private $scopeConfig;
    private $storeManager;
    private $moduleList;
    private $productMetadata;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Shipping\Model\Config $deliveryModelConfig,
        \Magento\Payment\Model\Config $paymentModelConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata

    )
    {
        $this->deliveryModelConfig = $deliveryModelConfig;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->paymentModelConfig = $paymentModelConfig;
    }     
    

    /**
     * Main method.
     */
    public function getInfo($responseData)
    {
        $setting = array(
            'webhooks' => array(
                'address' => $this->storeManager->getStore()->getUrl('m-shop/webhook/actions/') . 'get-address',
                'stock_count' => $this->storeManager->getStore()->getUrl('m-shop/webhook/actions/') . 'get-stock-count',
                'save_order' => $this->storeManager->getStore()->getUrl('m-shop/webhook/actions/') . 'save-order',
                'login' => $this->storeManager->getStore()->getUrl('m-shop/webhook/actions/') . 'login'
            ),
            'export' => array(
                'products' => $this->storeManager->getStore()->getUrl('m-shop/export/product'),
                'categories' => $this->storeManager->getStore()->getUrl('m-shop/export/category')
            ),
            'shipping' => $this->getShipping(),
            'payment' => $this->getPayment(),
            'system' => array(
                'platform' => 'Magento ' . $this->productMetadata->getVersion() . " | " . $this->productMetadata->getEdition(),
                'module_version' => $this->moduleList->getOne('Asymbo_Mshop')['setup_version'],
                'php_version' => phpversion()
            ),
            'additional' => array(
            )
        );
        $responseData->data = array('data' => $setting);
        return $responseData;
    }
    
    
    protected function getShipping()
    {
        $deliveries = array();
        $deliveryMethods = $this->deliveryModelConfig->getActiveCarriers();
        
        foreach ($deliveryMethods as $shippigCode => $shippingModel) { 
            $shippingTitle = $this->scopeConfig->getValue('carriers/'.$shippigCode.'/title');
            $deliveries[] = (object)array('name' => $shippingTitle, 'external_id' => $shippigCode . "_" . $shippigCode);
        }
        
        return $deliveries;
    }
    
    protected function getPayment()
    {
        $payments = array();
        $paymentMethods = $this->paymentModelConfig->getActiveMethods();
        foreach ($paymentMethods as $paymentCode=>$paymentModel) {
            $paymentTitle = $this->scopeConfig->getValue('payment/'.$paymentCode.'/title');
            $payments[] = (object)array('name' => $paymentTitle, 'external_id' => $paymentCode);
        }
        
        return $payments;
    }
}
