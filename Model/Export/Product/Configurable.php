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
namespace Asymbo\Plugin\Model\Export\Product;


/**
 * Product Url model
 *
 * @category   Asymbo
 * @package    Asymbo_Products
 * @author     Asymbo s.r.o.
 */
class Configurable {
    
    private $connection = null;
    private $resource = null;
    
    private $store = null;
    private $superAttributes = array();
    
    private $page = 0;
    private $childProducts = array();
    
    private $productCollection;
    private $simpleModel;
    private $preparedCategories;
    private $preparedPrices;
    private $preparedMedia;
    private $preparedAttributes;
    private $preparedRelationProducts;
    private $scopeConfig;
    
    public function __construct(
            \Asymbo\Plugin\Model\Export\Product\Simple $simpleModel,
            \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
            \Asymbo\Plugin\Model\Export\Product\Categories $preparedCategories,
            \Asymbo\Plugin\Model\Export\Product\Prices $preparedPrices,
            \Asymbo\Plugin\Model\Export\Product\Media $preparedMedia,
            \Magento\Framework\App\ResourceConnection $resource,
            \Asymbo\Plugin\Model\Export\Product\Attributes $preparedAttributes,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Asymbo\Plugin\Model\Export\Product\RelationProducts $preparedRelationProducts,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
            )
    {
        $this->resource = $resource;
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->simpleModel = $simpleModel;
        $this->productCollection = $productCollection;
        $this->preparedCategories = $preparedCategories;
        $this->preparedPrices = $preparedPrices;
        $this->preparedMedia = $preparedMedia;
        $this->preparedAttributes = $preparedAttributes;
        $this->store = $storeManager->getDefaultStoreView();
        $this->preparedRelationProducts = $preparedRelationProducts;
        $this->scopeConfig = $scopeConfig;
        
    }
    
    
    
    public function fetchConfigurableProducts($bulk, $xmlWriter) 
    {
        while(true) {
            $confirableProducts = $this->productCollection->create()
                     ->addAttributeToSelect('*')
                     ->addAttributeToFilter('type_id', array('eq' => 'configurable'))
                     ->addAttributeToFilter('visibility', array('in' => array(4,3,2)))
                     ->addStoreFilter($this->store);
            
            $confirableProducts->getSelect()->limit($bulk, $bulk * $this->page);
            $confirableProducts->load();
            
            $ids = array();
            foreach ($confirableProducts as $product) {
                $ids[] = $product['entity_id'];
            }
            
            $this->preparedCategories->assignCategoriesToProducts($confirableProducts);
            $this->preparedMedia->loadNewGallery($ids);
            
            $this->setChildProducts($ids);
            
            $this->preparedRelationProducts->loadAllCrossSell($ids);
            
            $this->prepareSuperAttributes($ids);
            
            foreach ($confirableProducts as $product) {
                $xmlWriter->startElement('SHOPITEM');
                    $this->simpleModel->setSimpleData($xmlWriter, $product);
                    $this->setVariants($xmlWriter, $product, $this->childProducts);
                $xmlWriter->endElement();
            }

            $this->page++;
            if(count($ids) === 0) {
                break;
            }
        }
    }
    
    public function setVariants($xml, $productModel, $childs)
    {
        if(isset($childs[$productModel['entity_id']])) {
            $childProducts = $childs[$productModel['entity_id']];

            //select all super attributes
            $superAttr = $this->superAttributes[$productModel['entity_id']];
 
            foreach($childProducts as $child) {
                $xml->startElement('VARIANT');
                    $xml->writeElement('ITEM_ID', $child['entity_id']);
                    $xml->writeElement('STOCK_NUMBER', $child['sku']);
                    
                    //add super attr to product
                    $addedOptions = $this->preparedAttributes->setVariantAttributes($xml, $child, array_keys($superAttr));
                              
                    $this->setDelivery($xml, $child);
                    //set price to super attr (price is from parent, no children)
                    $this->preparedPrices->setPrices($xml, $child);
                $xml->endElement();
            }
        }
    }
    
    protected function prepareSuperAttributes($ids)
    {
        if(!empty($ids)) {
            $superAttr = $this->connection->fetchAll(" SELECT * FROM "
                             . $this->resource->getTableName('catalog_product_super_attribute') . " AS `super_attr`
                             WHERE (super_attr.product_id IN (" . implode($ids, ",") . "))");

            foreach($superAttr as $attr) {
                $attrVal = ['attr_id' => $attr['attribute_id']];

                if(!isset($this->superAttributes[$attr['product_id']])) {
                    $this->superAttributes[$attr['product_id']] = array();
                }
                $this->superAttributes[$attr['product_id']][$attr['attribute_id']] = $attrVal;
            }
        } else {
            $this->superAttributes = array();
        }
        
        
    }
    
    
    protected function setChildProducts($ids) 
    {
        $childArr = array();
        if(!empty($ids)) {
            $subchilds = $this->connection->fetchAll("SELECT `e`.*, `relation`.`parent_id` FROM " 
                         . $this->resource->getTableName('catalog_product_entity') . " AS `e`
                         INNER JOIN " . $this->resource->getTableName('catalog_product_relation') . " AS `relation` ON relation.child_id = e.entity_id
                         LEFT JOIN " . $this->resource->getTableName('catalog_product_website') . " AS `product_website` ON product_website.product_id = e.entity_id 
                         WHERE (relation.parent_id IN (" . implode($ids, ",") . ")) AND (((`e`.`required_options` != '1') OR (`e`.`required_options` IS NULL)))");

            foreach($subchilds as $child) {                
                $childArr[] = $child['entity_id'];
            }
            
            
            $confirableProductsSub = $this->productCollection->create()
                     ->addAttributeToSelect('*')
                     ->addAttributeToFilter('entity_id', $childArr)
                     ->addStoreFilter($this->store)
                     ->joinTable('cataloginventory_stock_item',
                             'product_id=entity_id',
                             array('qty', 'backorders', 'use_config_backorders', 'is_in_stock'), 
                             '{{table}}.stock_id=1',
                             'left'
                         )
                     ->load();
            
            foreach ($confirableProductsSub as $produ) {
                $pr = $produ->toArray();
                
                foreach($subchilds as $child) {
                    if($child['entity_id'] == $pr['entity_id']) { 
                        $this->childProducts[$child['parent_id']][] = $produ;
                    }
                }
            }
        }
        return $childArr;
    }
    
    
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
