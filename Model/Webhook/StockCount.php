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

class StockCount
{
    
    private $productCollection;
    private $store;
    private $scopeConfig;
    
    public function __construct(
            \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
            )
    {
        $this->productCollection = $productCollection;
        $this->store = $storeManager->getDefaultStoreView();
        $this->scopeConfig = $scopeConfig;
    }
    
    public function getStockCount($data, $responseData) 
    {            
        if(!empty($data->products)) {
            $productsData = $this->loadProducts($data->products);
            $reorderedProducts = $this->reorderProducts($productsData);

            $finalProducts = array();
            foreach($data->products as $product) {
                $stockInfo = $this->setStockInfo($product, $reorderedProducts);
                if($stockInfo !== null) {
                    $finalProducts[] = $stockInfo;
                } 
            }

            $responseData->data = array('data' => array('products' => $finalProducts)); 
        } else {
            $responseData->http_code = 400;
        }
        return $responseData;
    }
    
    protected function loadProducts($productsData) {
        $productIds = array();
        
        foreach($productsData as $product) {
            $productIds[] = $product->id;
            if(isset($product->variants)) {
                foreach($product->variants as $var) {
                    $productIds[] = $var->id;
                }
            }
        }
        
        $products = $this->productCollection->create()
                     ->addAttributeToSelect('name')
                     ->addAttributeToFilter('entity_id', $productIds)
                     ->addStoreFilter($this->store)
                     ->joinTable('cataloginventory_stock_item',
                            'product_id=entity_id',
                            array('qty', 'backorders', 'use_config_backorders', 'is_in_stock'), 
                            '{{table}}.stock_id=1',
                            'left'
                         )
                     ->load();
        return $products;
    }
    
    
    protected function setStockInfo($product, $products)
    {
        $return = null;
        if(isset($product->variants)) {
            $variants = array();
            foreach($product->variants as $variant) {
                $variantStock =  $this->setStockInfo($variant, $products);
                if($variantStock !== null) {
                    $variants[] = $variantStock;
                }
            }
            if(isset($products[$product->id])) {
                $return = array(
                    'id' => (string)$products[$product->id]['entity_id'],
                    'variants' => $variants
                );   
            }
        } else if(isset($products[$product->id])) { 
            $return = array(
                'id' => (string)$products[$product->id]['entity_id'],
                'name' => $products[$product->id]['name']
            );
            if(isset($products[$product->id]['is_stock'])) {
                $return['is_stock'] = $products[$product->id]['is_stock'];
            }
            if(isset($products[$product->id]['stock_count'])) {
                $return['stock_count'] = $products[$product->id]['stock_count'];
            }
        }

        return $return;
    }
    
    
    protected function reorderProducts($products) {
        $return = array();
        foreach($products as $productObject) {
            $product = $productObject->toArray();
            $return[$product['entity_id']]['entity_id'] = $product['entity_id'];
            if($product['use_config_backorders'] == 1) {
                $backOrder = $this->scopeConfig->getValue('cataloginventory/item_options/backorders', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            } else {
                $backOrder = $product['backorders'];
            }
            
            if (!$product['is_salable'] == 1 || $product['is_in_stock'] == 0 || ($backOrder == 0 && $product['qty']<=0)) {
                $return[$product['entity_id']]['name'] = __('Out of Stock');
                $return[$product['entity_id']]['is_stock'] = false;
                $return[$product['entity_id']]['stock_count'] = 0;
            } else {
                if($backOrder == 2 && $product['qty']<=0) {
                    $return[$product['entity_id']]['is_stock'] = false;
                } else {
                    $return[$product['entity_id']]['is_stock'] = true;
                }
                $return[$product['entity_id']]['name'] = __('In Stock');
                if($product['qty'] > 0) {
                    $return[$product['entity_id']]['stock_count'] = round($product['qty']);
                }
            }
        }
        
        return $return;
    }
}
