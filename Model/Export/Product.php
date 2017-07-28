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
namespace Asymbo\Plugin\Model\Export;



class Product
{
    private $simpleModel;
    private $configurableModel;
    
    public function __construct(\Asymbo\Plugin\Model\Export\Product\Simple $simpleModel,
            \Asymbo\Plugin\Model\Export\Product\Configurable $configurableModel)
    {
        $this->simpleModel = $simpleModel;
        $this->configurableModel = $configurableModel;
    }
    
    const XML_BASE = '<?xml version="1.0" encoding="UTF-8"?><SHOP></SHOP>';
    
   
    
    public function exportAll($exportParams) 
    {
        $xmlWriter = new \XMLWriter();
        $xmlWriter->openURI('php://output');
        $xmlWriter->setIndent( true );
        $xmlWriter->startDocument( '1.0', 'utf-8' );
        $xmlWriter->startElement( 'SHOP' );
            $this->simpleModel->fetchSimpleProducts($exportParams['bulk'], $xmlWriter);
            $this->configurableModel->fetchConfigurableProducts($exportParams['bulk'], $xmlWriter);
        $xmlWriter->endElement();
        $xmlWriter->endDocument();
    }
}
