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

class Shopix_AddToCartMatrix_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getProductMatrixData($product)
    {
        $matrix = new Varien_Object();

        // TODO make attributes configurable
        $attr_primary = 'size';
        $attr_secondary = 'color';
        $stock_cutoff = 200;

        $required_attr_codes = array($attr_primary, $attr_secondary);

        $configurable_attributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
        $product_attr_codes = array();
        foreach ($configurable_attributes as $attr) {
            $product_attr_codes[] = $attr['attribute_code'];
        }

        if (0 !== count(array_diff($required_attr_codes, $product_attr_codes)))
            return $matrix;

        foreach ($configurable_attributes as $attr) {
            if ($attr['attribute_code'] == $attr_primary)
                $matrix->setPrimaryHeader(new Varien_Object($attr));
            if ($attr['attribute_code'] == $attr_secondary)
                $matrix->setSecondaryHeader(new Varien_Object($attr));
        }

        $res_model = Mage::getModel('catalog/product')->getResource();
        $attributes = array();
        foreach ($required_attr_codes as $code)
            $attributes[] = $res_model->getAttribute($code);

        $required_attribute_ids = array();
        foreach ($attributes as $attr)
            $required_attribute_ids[] = $attr->getId();

        $children = Mage::getModel('catalog/product_type_configurable')->getUsedProducts($required_attribute_ids, $product);

        $header = array();
        $matrix_data = array();
        foreach ($children as $child) {
            foreach ($attributes as $attr)
                $header[$attr->getAttributeCode()][$child->getData($attr->getAttributeCode())] = 1;

            $matrix_data[$child->getData($attributes[0]->getAttributeCode())][$child->getData($attributes[1]->getAttributeCode())] = $child;
        }

        $rows = array();
        foreach ($header[$attr_secondary] as $secondary => $ignore) {
            $row = new Varien_Object();
            $cells = array();
            foreach ($header[$attr_primary] as $primary => $ignore) {
                $cell = new Varien_Object();
                if (! isset($matrix_data[$primary][$secondary]) || ! $matrix_data[$primary][$secondary]->isSaleable()) {
                    $cell->setIsDisabled(true);
                    $cell->setProduct(new Varien_Object());
                    $cell->setStockLevel(0);
                    $cell->setStockLevelLabel('N/A');
                } else {
                    $child = $matrix_data[$primary][$secondary];
                    $cell->setProduct($child);
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($child)->getQty();
                    $cell->setStockLevel($stock);
                    if ((int) $stock == (float) $stock)
                        $stock = (int) $stock;
                    $cell->setStockLevelLabel(($stock <= $stock_cutoff) ? $stock : ($stock_cutoff . '+'));
                    $cell->setSuperAttributes(array(
                        new Varien_Object(array('name' => $res_model->getAttribute($attr_primary)->getId(), 'value' => $primary)),
                        new Varien_Object(array('name' => $res_model->getAttribute($attr_secondary)->getId(), 'value' => $secondary)),
                    ));
                }
                $cells[] = $cell;
            }
            foreach ($matrix->getSecondaryHeader()->getValues() as $sec_head)
                if ($sec_head['value_index'] == $secondary)
                    $row->setHeader(new Varien_Object($sec_head));
            $row->setCells($cells);
            $rows[] = $row;
        }
        $matrix->setRows($rows);

        return $matrix;
    }

}

