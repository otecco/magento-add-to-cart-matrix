<?php
/*
 *  Shopix_AddToCartMatrix - Magento Add to Cart Matrix
 *  Copyright (C) 2015 Shopix Pty Ltd (http://www.shopix.com.au)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Shopix_AddToCartMatrix_CartController extends Mage_Core_Controller_Front_Action
{
    public function addAllAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_forward('noRoute');
            return;
        }

        $data = $this->getRequest()->getParam('shopix_addtocartmatrix_product');

        $configurable_id = $data['configurable'];
        unset($data['configurable']);
            
        $products = array_filter($data, create_function('$e', 'return (int) $e["qty"] > 0;'));

        $cart = Mage::getSingleton('checkout/cart'); 
        $cart->init();
        foreach ($products as $id => $product) {
            $buy_request = array(
                'product' => $configurable_id,
                'qty' => $product['qty'],
                'super_attribute' => $product['attr']
            );
            try {
                $cart->addProduct($configurable_id, $buy_request);
            } catch (Mage_Core_Exception $e) {
                $session = Mage::getSingleton('checkout/session');
                if ($session->getUseNotice(true)) {
                    $session->addNotice($e->getMessage());
                } else {
                    $messages = array_unique(explode("\n", $e->getMessage()));
                    foreach ($messages as $message) {
                        $session->addError($message);
                    }
                }

                $url = $session->getRedirectUrl(true);
                if ($url) {
                    $this->getResponse()->setRedirect($url);
                } else {
                    $this->_redirectReferer(Mage::helper('checkout/cart')->getCartUrl());
                }
            }
        }
        $cart->save();
        Mage::getSingleton('checkout/session')->setCartWasUpdated(true);

        return $this->_redirect('checkout/cart');
    }
}

