<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Translate factory
 */
namespace Magento\Translate;

class Factory
{
    /**
     * Default translate inline class name
     */
    const DEFAULT_CLASS_NAME = 'Magento\Translate\InlineInterface';

    /**
     * Object Manager
     *
     * @var \Magento\ObjectManager
     */
    protected $_objectManager;

    /**
     * Object constructor
     * @param \Magento\ObjectManager $objectManager
     */
    public function __construct(\Magento\ObjectManager $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    /**
     * Return instance of inline translate object based on passed in class name.
     *
     * @param array $data
     * @param string $className
     * @throws \InvalidArgumentException
     * @return \Magento\Translate\InlineInterface
     */
    public function create(array $data = null, $className = null)
    {
        if ($className === null) {
            $className = self::DEFAULT_CLASS_NAME;
        }
        $model = $this->_objectManager->get($className, $data);
        if (!$model instanceof \Magento\Translate\InlineInterface) {
            throw new \InvalidArgumentException('Invalid inline translate model: ' . $className);
        }
        return $model;
    }
}
