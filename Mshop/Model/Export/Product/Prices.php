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
namespace Asymbo\Mshop\Model\Export\Product;

/**
 * Product Url model
 *
 * @category   Asymbo
 * @package    Asymbo_Products
 * @author     Asymbo s.r.o.
 */
class Prices {
    
       
    private $connection = null;
    private $store = null;
    private $groupPrices = null;
    private $customerGroups = array();
    private $taxCalculation;
    private $customerGroupsCollection;
    private $catalogHelper;
    
    public function __construct(
            \Magento\Framework\App\ResourceConnection $resource,
            \Magento\Tax\Model\TaxCalculation $taxCalculation,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Magento\Customer\Model\Group $customerGroupsCollection,
            \Magento\Catalog\Helper\Data $catalogHelper
            )
    {
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->taxCalculation = $taxCalculation;
        $this->store = $storeManager->getDefaultStoreView();
        $this->customerGroupsCollection = $customerGroupsCollection;
        $this->catalogHelper = $catalogHelper;
        //$this->loadAllGroups();
    }
    
    
//    protected function loadAllGroups()
//    {
//        $groups = $this->customerGroupsCollection->getCollection()->toArray();
//        foreach($groups['items'] as $group) {
//            $this->customerGroups[$group['customer_group_id']] = $group;
//        }
//    }
    
    public function setPrices($xml, $product)
    {
        if($product['type_id'] != 'configurable' ) {
            $taxClassId = $product->getTaxClassId();
            $product->setTaxClassId($taxClassId);
            $percent = $this->taxCalculation->getCalculatedRate($taxClassId);

            $todayDate = strtotime(date("Y-m-d H:i:s"));
            $specialDateBegin = strtotime($product['special_from_date']);
            if(!empty($product['special_to_date'])) {
                $specialDateEnd = strtotime($product['special_to_date']);
            }
           
            if($todayDate > $specialDateBegin && isset($specialDateEnd) && $todayDate < $specialDateEnd) {
                $specialPrice = $product->getSpecialPrice();
            } else {
                $specialPrice = null;
            }
            
            if($specialPrice != null && ($product->getPrice() > $specialPrice)) {
                $price = $specialPrice;
                $taxCommonPrice = $this->catalogHelper->getTaxPrice($product, $product->getPrice(), true, null,null,null,$this->store,true,true);
                $noTaxCommonPrice = $this->catalogHelper->getTaxPrice($product, $product->getPrice(), false, null,null,null,$this->store,true,true);
                $xml->writeElement('PRICE_COMMON', $noTaxCommonPrice);
                $xml->writeElement('PRICE_COMMON_VAT', $taxCommonPrice);
            } else {
                $price = $product->getPrice();
            }
            
            $taxPrice = $this->catalogHelper->getTaxPrice($product, $price, true, null,null,null,$this->store,true,true);
            $noTaxPrice = $this->catalogHelper->getTaxPrice($product, $price, false, null,null,null,$this->store,true,true);

            $xml->writeElement('PRICE', $noTaxPrice);
            $xml->writeElement('PRICE_VAT', $taxPrice);
            $xml->writeElement('VAT', $percent / 100);
        }
//        $this->setPriceGroups($xml, $product);
        
    }
    
    /* Price groups disabled because we cant do saoe for some number of pieces
     * 
    protected function setPriceGroups($xml, $product, $super = null)
    {
        if(!empty($this->groupPrices[$product['entity_id']])) {
            $originalTaxClass = $product->getTaxClassId();
            $originalTaxPercent = $product->getTaxPercent();
            
            foreach($this->groupPrices[$product['entity_id']] as $group) {
                //$taxCalculation = Mage::getModel('tax/calculation');
                $taxClassId = $this->customerGroups[$group['customer_group_id']]['tax_class_id'];
                $percent = $this->taxCalculation->getCalculatedRate($taxClassId);
                $product->setTaxClassId($taxClassId);
                $product->setTaxPercent($percent);
                
                if(is_null($super)) {
                    $price = $group['group_price'];
                } else {
                    $price = $this->numberSuperPrice($super['super'], $super['options'], $group['group_price']);
                }
                
                $taxPrice = $this->catalogHelper->getTaxPrice($product, $product->getPrice(), true, null,null,null,$this->store,true,true);
                $noTaxPrice = $this->catalogHelper->getTaxPrice($product, $product->getPrice(), false, null,null,null,$this->store,true,true);
                
                $groupPrice = $xml->addChild('PRICE_GROUP');
                $groupPrice->addChild('ID', $group['customer_group_id']);
                $groupPrice->addChild('PRICE', $noTaxPrice);
                $groupPrice->addChild('VAT', $percent / 100);
                $groupPrice->addChild('PRICE_VAT', $taxPrice);
            }
            
            $product->setTaxClassId($originalTaxClass);
            $product->setTaxPercent($originalTaxPercent);
        }
    }*/
    
    
    /**
     * Prepare products group prices
     *
     * @param  array $productIds
     * @return array
     */
    /*
    public function prepareGroupPrices(array $productIds)
    {
        if (empty($productIds)) {
            $this->groupPrices = array();
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->connection->select()
            ->from($resource->getTableName('catalog/product_attribute_group_price'))
            ->where('entity_id IN(?)', $productIds);

        $rowGroupPrices = array();
        $statement = $this->connection->query($select);
        while ($groupRow = $statement->fetch()) {
            $rowGroupPrices[$groupRow['entity_id']][] = array(
                'customer_group_id' => $groupRow['all_groups']
                    ? 'all'
                    : $groupRow['customer_group_id'],
                '_group_price_website'        => (0 == $groupRow['website_id'])
                    ? 'all'
                    : true,//$this->_websiteIdToCode[$groupRow['website_id']],
                'group_price'          => $groupRow['value']
            );
        }

        $this->groupPrices = $rowGroupPrices;
    } */
}
