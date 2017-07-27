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
namespace Asymbo\Mshop\Controller\Export;

use \Magento\Framework\App\Action\Action;


class Product extends Action
{

    const BULK_PRODUCTS = 500;
    private $exportParams = array('bulk' => null, 'show_errors' => false);

    public function execute() { 
        $this->setInitOptions();
        header('Powered-By: Magento');
        header("Content-type: text/xml");
        $this->_objectManager->create('Asymbo\Mshop\Model\Export\Product')->exportAll($this->exportParams);
        exit;
    }
    
    protected function setInitOptions()
    {
        $this->setParams();
        if(function_exists('error_reporting')) {
            if($this->exportParams['show_errors']) {
                error_reporting(E_ALL);
            } else {
                error_reporting(0);
            }
        }
        if(function_exists('set_time_limit')) {
            set_time_limit(200);
        }
    }

    
    protected function setParams() 
    {
        if(isset($_GET['bulk']) && intval($_GET['bulk']) > 0) {
            $this->exportParams['bulk']  = intval($_GET['bulk']);
        } else {
            $this->exportParams['bulk']  = self::BULK_PRODUCTS;
        }
        
        if(isset($_GET['show_errors'])) {
            $this->exportParams['show_errors'] = true;
        }
                
    }
}