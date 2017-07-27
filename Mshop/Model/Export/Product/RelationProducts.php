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
class RelationProducts {
    
       
    private $connection = null;
    private $store = null;
    private $resource;
    
    private $crossSellRelations = array();
    
    
    public function __construct(
            \Magento\Framework\App\ResourceConnection $resource,
            \Magento\Store\Model\StoreManagerInterface $storeManager
            )
    {
        $this->resource = $resource;
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->store = $storeManager->getDefaultStoreView();
    }
        
    
    public function loadAllCrossSell($ids)
    {
        if(!empty($ids)) {
            $relations = $this->connection->fetchAll("SELECT * FROM " . $this->resource->getTableName('catalog_product_link') . " WHERE product_id IN (" . implode($ids, ",") . ") AND link_type_id=5");
            foreach($relations as $rel) {
                $this->crossSellRelations[$rel['product_id']][] = $rel['linked_product_id'];
            }
        } else {
            $this->crossSellRelations = array();
        }
    }
    
    public function setCrossSell($xml, $product)
    {
        if(isset($this->crossSellRelations[$product['entity_id']])) {
            $xml->startElement('RELATION');  
                $xml->writeElement('NAME', 'Related products');
                $xml->writeElement('TYPE', 'related');

                $xml->startElement('WIDGET');
                    $xml->writeElement('SCREEN', 'cart_item_detail');
                    $xml->writeElement('LIST_TYPE' , 'carousel');
                $xml->endElement();
               
                foreach($this->crossSellRelations[$product['entity_id']] as $productId) {
                    $xml->startElement('SHOPITEM');
                        $xml->writeElement('ITEM_ID', $productId);
                    $xml->endElement();
                }
            $xml->endElement();
        }
    }
}
