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


class Category extends Action
{


    public function execute() { 
        $this->setInitOptions();
        $this->getResponse()->setHeader('Content-type', 'text/xml');
        $this->_objectManager->create('Asymbo\Mshop\Model\Export\Category')->exportAll();
    }

    
    protected function setInitOptions()
    {
        error_reporting(0);
        if(function_exists('set_time_limit')) {
            set_time_limit(1000);
        }
    }
}