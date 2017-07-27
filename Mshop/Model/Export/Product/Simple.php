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
class Simple {
    
    private $productCollection;
    private $preparedCategories;
    private $preparedPrices;
    private $preparedMedia;
    private $preparedAttributes;
    private $preparedRelationProducts;
    private $productUrlGenerator;
    private $store;
    private $scopeConfig;
    
    public function __construct(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
                                \Asymbo\Mshop\Model\Export\Product\Categories $preparedCategories,
                                \Asymbo\Mshop\Model\Export\Product\Prices $preparedPrices,
                                \Asymbo\Mshop\Model\Export\Product\Media $preparedMedia,
                                \Asymbo\Mshop\Model\Export\Product\Attributes $preparedAttributes,
                                \Asymbo\Mshop\Model\Export\Product\RelationProducts $preparedRelationProducts,
                                \Magento\Catalog\Model\Product\Url $productUrlGenerator,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
            )
    {
        $this->productCollection = $productCollection;
        $this->preparedCategories = $preparedCategories;
        $this->preparedPrices = $preparedPrices;
        $this->preparedMedia = $preparedMedia;
        $this->preparedAttributes = $preparedAttributes;
        $this->productUrlGenerator = $productUrlGenerator;
        $this->scopeConfig = $scopeConfig;
        
        $this->preparedRelationProducts = $preparedRelationProducts;
        $this->store = $storeManager->getDefaultStoreView();
    }
    
    /**
     * Fetch all simple products
     */
    public function fetchSimpleProducts($bulk, $xmlWriter) 
    { 
        $pageNumber = 0;
        while(true) {            
            $simpleProducts = $this->productCollection->create()
                     ->addAttributeToSelect('*')
                     ->addAttributeToFilter('type_id', array('eq' => 'simple'))
                     ->addAttributeToFilter('visibility', array('in' => array(4,3,2)))
                     ->addStoreFilter($this->store)
                     ->joinTable('cataloginventory_stock_item',
                        'product_id=entity_id',
                        array('qty', 'backorders', 'use_config_backorders', 'is_in_stock'), 
                        '{{table}}.stock_id=1',
                        'left'
                     ); 
            $simpleProducts->getSelect()->limit($bulk, $bulk * $pageNumber);
            $simpleProducts->load();
                      
            $ids = array();
            foreach ($simpleProducts as $product) {
                $ids[] = $product['entity_id'];
            }
            
            $this->preparedCategories->assignCategoriesToProducts($simpleProducts);
            $this->preparedMedia->loadNewGallery($ids);
            $this->preparedRelationProducts->loadAllCrossSell($ids);
            
            foreach ($simpleProducts as $product) {
                $xmlWriter->startElement('SHOPITEM');
                    $this->setSimpleData($xmlWriter, $product);
                $xmlWriter->endElement();
            }
   
            $pageNumber++;
            if(count($ids) === 0) {
                break;
            }
        }
    }
    
    /**
     * Set simple data of product to XML
     * 
     * @param SimpleXML $xml
     * @param Mage_Catalog_Model_Product $product
     */
    public function setSimpleData($xml, $product) 
    {
        //product ID
        $xml->writeElement('ITEM_ID', $product['entity_id']);
        //stock number
        $xml->writeElement('STOCK_NUMBER',$product['sku']);
        //product name
        $xml->writeElement('PRODUCT', $product['name']);
        //weight
        if($product['weight']) {
            $xml->writeElement('WEIGHT', $product['weight']);    
        }
        //category path
        $this->preparedCategories->setProductCategories($xml, $product);     
        //description
        $xml->startElement('DESCRIPTION');
            $xml->writeCdata($product['description']);
        $xml->endElement(); 
        //descritpion short
        $xml->startElement('DESCRIPTION_SHORT');
            $xml->writeCdata($product['short_description']);
        $xml->endElement(); 
        
        $xml->writeElement('URL', $this->productUrlGenerator->getProductUrl($product));

        //adding pictures   
        $this->preparedMedia->setImages($xml, $product);      
        
        //adding prices     
        $this->preparedPrices->setPrices($xml, $product);

        //delivery
        $this->setDelivery($xml, $product);
        
        //params
        $this->preparedAttributes->setFrontAttributes($xml, $product);
       
        $this->preparedRelationProducts->setCrossSell($xml, $product);
        
    }
    
    /**
     * Insert delivery information to XML
     * 
     * @param SimpleXML $xml
     * @param Mage_Catalog_Model_Product $product
     */
    protected function setDelivery($xml, $product) 
    {
        
        $xml->startElement('DELIVERY');
        if($product['use_config_backorders'] == 1) {
            $backOrder = $this->scopeConfig->getValue('cataloginventory/item_options/backorders', \Magento\Store\Model\ScopeInterface::SCOPE_STORE); 
        } else {
            $backOrder = $product['backorders'];
        }
        
        if (!$product['is_salable'] == 1 || $product['is_in_stock'] == 0 || ($backOrder == 0 && $product['qty']<=0)) {
            $xml->writeElement('NAME', __('Out of Stock'));
            $xml->writeElement('IS_STOCK', 0);     
        } else {
            if($backOrder == 2 && $product['qty']<=0) {
                $xml->writeElement('IS_STOCK', 0);
            } else {
                $xml->writeElement('IS_STOCK', 1);  
            }
            $xml->writeElement('NAME', __('In Stock'));
            if($product['qty'] > 0) {
                $xml->writeElement('STOCK_COUNT', round($product['qty']));   
            }
        }
        $xml->endElement(); 
    }
}