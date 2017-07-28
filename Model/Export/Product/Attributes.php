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
class Attributes {
    
    /**
     * Attributes with index (not label) value.
     *
     * @var array
     */
    protected $indexValueAttributes = array(
        'status',
        'tax_class_id',
        'visibility',
        'gift_message_available',
        'custom_design'
    );
    
    public $frontAttributes = array();
    public $variantAttributes = array();
    public $attributeTypes = array();

    private $attributeCollectionFactory;
    
    public function __construct(
            \Magento\ImportExport\Model\Export\Factory $collectionFactory
            )
    {
        $this->attributeCollectionFactory = $collectionFactory->create('Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection');
        
        $this->loadAllAttributes();
    }
    
    /**
     * Returns attributes all values in label-value or value-value pairs form. Labels are lower-cased.
     *
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     * @return array
     */
    public function getAttributeOptions(\Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute)
    {
        $options = [];

        if ($attribute->usesSource()) {
            // should attribute has index (option value) instead of a label?
            $index = in_array($attribute->getAttributeCode(), $this->indexValueAttributes) ? 'value' : 'label';

            // only default (admin) store values used
            $attribute->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);

            try {
                foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                    foreach (is_array($option['value']) ? $option['value'] : [$option] as $innerOption) {
                        if (strlen($innerOption['value'])) {
                            // skip ' -- Please Select -- ' option
                            $options[$innerOption['value']] = (string)$innerOption[$index];
                        }
                    }
                }
            } catch (\Exception $e) {
                // ignore exceptions connected with source models
            }
        }
        return $options;
    }
    
    public function getAttributeCollection()
    {
        return $this->attributeCollectionFactory;
    }
    
    public function loadAllAttributes() 
    {
        foreach ($this->getAttributeCollection() as $attribute) {
            $attrArray = $attribute->toArray();
            if($attrArray['is_visible_on_front'] == 1 || $attrArray['is_filterable'] > 0) {
                $this->frontAttributes[$attrArray['attribute_code']]['options'] = $this->getAttributeOptions($attribute);
                $this->frontAttributes[$attrArray['attribute_code']]['label'] = $attrArray['frontend_label'];
                $this->frontAttributes[$attrArray['attribute_code']]['in_filter'] = $attrArray['is_filterable'] > 0 ? '1' : '0';
                $this->frontAttributes[$attrArray['attribute_code']]['is_property'] = $attrArray['is_visible_on_front'];
            }

            if($attrArray['is_global'] == 1) { 
                $this->variantAttributes[$attrArray['attribute_code']]['options'] = $this->getAttributeOptions($attribute);
                $this->variantAttributes[$attrArray['attribute_code']]['label'] = $attrArray['frontend_label'];
                $this->variantAttributes[$attrArray['attribute_code']]['attr_id'] = $attrArray['attribute_id'];
                $this->variantAttributes[$attrArray['attribute_code']]['in_filter'] = $attrArray['is_filterable'] > 0 ? '1' : '0';
            }
             $this->attributeTypes[$attrArray['attribute_code']] = \Magento\ImportExport\Model\Import::getAttributeType($attribute);
        }
    }
    
    public function setFrontAttributes($xml, $product)
    {
        foreach ($this->frontAttributes as $attrCode => $attr) {                   
            $attrValue = $product->getData($attrCode);            
            if(!empty($attrValue)) {
                $value = "";
                if($this->attributeTypes[$attrCode] == 'select') {
                    $xml->startElement('PARAM');
                        $xml->writeElement('NAME', $attr['label']);
                        $xml->writeElement('VALUE', $attr['options'][$attrValue]);
                        $xml->writeElement('IN_FILTER', $attr['in_filter']);
                        $xml->writeElement('IS_PROPERTY', $attr['is_property']);
                    $xml->endElement(); 
                } elseif($this->attributeTypes[$attrCode] == 'multiselect') {
                    $valuesArr = explode(",", $attrValue);
                    foreach($valuesArr as $k) {
                        $xml->startElement('PARAM');
                            $xml->writeElement('NAME', $attr['label']);
                            $xml->writeElement('VALUE', $attr['options'][$k]);
                            $xml->writeElement('IN_FILTER', $attr['in_filter']);
                            $xml->writeElement('IS_PROPERTY', $attr['is_property']);
                        $xml->endElement();
                    }
                } elseif(!is_array($attrValue)) {
                    $xml->startElement('PARAM');
                        $xml->writeElement('NAME', $attr['label']);
                        $xml->writeElement('VALUE', $attrValue);
                        $xml->writeElement('IN_FILTER', $attr['in_filter']);
                        $xml->writeElement('IS_PROPERTY', $attr['is_property']);
                    $xml->endElement();                    
                }
                
                
            }
        } 
    }
    
    public function setVariantAttributes($xml, $productModel, $super)
    {
        $returnAttr = array();
        foreach ($this->variantAttributes as $attrCode => $attr) {
            if(isset($productModel[$attrCode]) && !empty($productModel[$attrCode]) && in_array($attr['attr_id'],$super)) {
                
                $value = "";
                if($this->attributeTypes[$attrCode] == 'select') {
                    $returnAttr[] = $productModel[$attrCode];
                    $value = $attr['options'][$productModel[$attrCode]];
                } elseif($this->attributeTypes[$attrCode] == 'multiselect') {
                    $valuesArr = explode(",", $attrValue);
                    foreach($valuesArr as $k) {
                        $value !== "" ? $value = $value . ", " : true; 
                        $value = $value . ($attr['options'][$k]);
                    }
                } else {
                    $value = $productModel[$attrCode];
                }
                
                $xml->startElement('PARAM');
                    $xml->writeElement('NAME', $attr['label']);
                    $xml->writeElement('VALUE', $value);
                    $xml->writeElement('IN_FILTER', $attr['in_filter']);
                    $xml->writeElement('IS_VARIANT', 1);
                $xml->endElement();
            }
        }
        return $returnAttr;
    }
}
