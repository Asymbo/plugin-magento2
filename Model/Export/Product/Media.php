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
class Media {

    private $connection = null;
    
    public $currentMediaGallery = array();
    private $store = null;
    private $resource;
    private $mediaUrl;
    
    public function __construct(
            \Magento\Framework\App\ResourceConnection $resource,
            \Magento\Store\Model\StoreManagerInterface $storeManager
            )
    {
        $this->resource = $resource;
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->store = $storeManager->getDefaultStoreView();
        $this->mediaUrl = $this->store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        
    }
    
     /**
     * Prepare products media gallery
     *
     * @param  array $productIds
     * @return array
     */
    public function loadNewGallery(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }
        
        $select = $this->connection->select()
                ->from(
                        array('mg' => $this->resource->getTableName('catalog_product_entity_media_gallery')),
                        array(
                            'mgv.entity_id', 'mg.attribute_id', 'filename' => 'mg.value', 'mgv.label',
                            'mgv.position', 'mgv.disabled'
                        )
                )
                ->joinLeft(
                        array('mgv' => $this->resource->getTableName('catalog_product_entity_media_gallery_value')),
                        '(mg.value_id = mgv.value_id AND mgv.store_id = 0)',
                        array()
                )
                ->where('entity_id IN(?)', $productIds)
                ->where('mgv.disabled=0')
                ->order('position');

        $rowMediaGallery = array();
        $stmt = $this->connection->query($select);
        while ($mediaRow = $stmt->fetch()) {
            $rowMediaGallery[$mediaRow['entity_id']][] = array(
                '_media_attribute_id'   => $mediaRow['attribute_id'],
                '_media_image'          => $mediaRow['filename'],
                '_media_lable'          => $mediaRow['label'],
                '_media_position'       => $mediaRow['position'],
                '_media_is_disabled'    => $mediaRow['disabled']
            );
        }

        $this->currentMediaGallery = $rowMediaGallery;
    }
    
    
    /**
     * 
     * @param SimpleXML $xml
     * @param Mage_Catalog_Model_Product $product
     */
    public function setImages($xml, $product)
    {
        $isFirstImage = true;
        if(isset($this->currentMediaGallery[$product['entity_id']])) {
            
            
            
        $media = $this->currentMediaGallery[$product['entity_id']];
            foreach ($media as $me) {
                $url = $this->mediaUrl . 'catalog/product' . $me["_media_image"];
                if($isFirstImage) {
                    $xml->writeElement('IMGURL', $url);
                } else {
                    $xml->writeElement('IMGURL_ALTERNATIVE', $url);
                }
                $isFirstImage = false;
            }
        }
    }
}
