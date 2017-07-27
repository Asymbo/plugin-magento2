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
class Categories {

    protected $categories = array();
    
    private $categoryCollectionFactory;
    private $connection = null;
    private $resource;
    
    public function __construct(
                \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
                \Magento\Framework\App\ResourceConnection $resource
            )
    {
        $this->resource = $resource;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->prepareCategories();
    }
    
    /**
     * Prepare all categories with path
     */
    protected function prepareCategories() 
    {
        $collection = $this->categoryCollectionFactory->create()
                           ->addAttributeToSelect('*');
        
        foreach ($collection as $category) {
           
            $structure = preg_split('#/+#', $category->getPath());
            $pathSize  = count($structure);
            if ($pathSize > 1) {
                 
                $path = array();
                $pathUrl = array();
                for ($i = 1; $i < $pathSize; $i++) {
                    $pathUrl[] = $collection->getItemById($structure[$i])->formatUrlKey($collection->getItemById($structure[$i])->getName());
                    $path[] = $collection->getItemById($structure[$i])->getName();
                }
                array_shift($path);
                array_shift($pathUrl);
                if ($pathSize > 2) {
                    $this->categories[$category->getId()]['path'] = implode(' | ', $path);
                    $this->categories[$category->getId()]['url'] = implode('/', $pathUrl);
                }
            }
        }
    }
    
    /**
     * 
     * @param SimpleXml $xml
     * @param Mage_Catalog_Model_Product $product
     */
    public function setProductCategories($xml, $product)
    {
        if(is_array($product['category_ids'])) {
            foreach($product['category_ids'] as $cat) {
                if(isset($this->categories[$cat])) {
                    $xml->writeElement('CATEGORYTEXT', $this->categories[$cat]['path']);
                }
            }
        }
    }
    
    /**
     * 
     * @param Mage_Catalog_Model_Product $products
     */
    public function assignCategoriesToProducts($products)
    {
        $ids = array();
        
        foreach ($products as $product) {
            $ids[] = $product['entity_id'];
        }
        
        if(!empty($ids)) {
            $categories = $this->connection->fetchAll("SELECT * FROM " . $this->resource->getTableName('catalog_category_product') . " WHERE product_id IN (" . implode($ids, ",") . ")");
            $catArray = array();
            foreach($categories as $cat) {
                $catArray[$cat['product_id']][] = $cat['category_id'];
            }

            foreach ($products as $product) {
                if(isset($catArray[$product['entity_id']])) {
                    $product->setCategoryIds($catArray[$product['entity_id']]);
                } else {
                    $product->setCategoryIds(array());
                }
            }
        }
    }
}
