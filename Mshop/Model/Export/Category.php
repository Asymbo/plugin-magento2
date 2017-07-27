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
namespace Asymbo\Mshop\Model\Export;



class Category
{
    private $_categoryCollectionFactory;
    private $store = null;
    private $rootCategoryId;
    
    public function __construct(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
                                \Magento\Store\Model\StoreManagerInterface $storeManager
            )
    {
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->store = $storeManager->getDefaultStoreView();
        $this->website = $storeManager->getWebsite($this->store->getWebsiteId());
        $this->rootCategoryId = $storeManager->getGroup($this->website->getDefaultGroupId())->getRootCategoryId();
        
    }
    
    public function exportAll() 
    {

        $collection = $this->_categoryCollectionFactory->create()
            ->addAttributeToSelect('*');
                
        //create basic xml
        $xmlWriter = new \XMLWriter();
        $xmlWriter->openURI('php://output');
        $xmlWriter->setIndent( true );
        $xmlWriter->startDocument( '1.0', 'utf-8' );
        $xmlWriter->startElement( 'SHOP' );
        $rootCat = null;

        $allCategories = array();
        foreach ($collection as $category) {
            $cat = $category->toArray();
            if($cat['entity_id'] == $this->rootCategoryId) {
                $rootCat = $cat;
            }
            $allCategories[] = $cat;
        }
        $this->getProductCategoryPath($rootCat, $xmlWriter, $allCategories);
        $xmlWriter->endElement();
        $xmlWriter->endDocument(); 
    }
    
    
    /**
     * Recursively creating category tree
     *
     * @param array $category
     * @param SimpleXMLElement $xml
     * @param array $allCategories
     * @return SimpleXMLElement
     */
    protected function getProductCategoryPath($category, $xmlWriter, $allCategories)
    {
        foreach ($allCategories as $cat) {
            if ($cat['parent_id'] == $category['entity_id']) {
                $xmlWriter->startElement('CATEGORY');
                    $xmlWriter->writeElement('NAME',$cat['name']);
                    $this->getProductCategoryPath($cat, $xmlWriter, $allCategories);
                $xmlWriter->endElement();
            }
        }
        return $xmlWriter;
    }
}
