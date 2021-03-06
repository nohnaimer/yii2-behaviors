<?php

namespace lav45\behaviors;

use lav45\behaviors\contracts\AttributeChangeInterface;
use lav45\behaviors\contracts\OldAttributeInterface;
use lav45\behaviors\contracts\AttributeInterface;

/**
 * Trait VirtualAttributesTrait
 * @package lav45\behaviors
 */
trait VirtualAttributesTrait
{
    /**
     * @param string $name
     * @param string $type the class/interface
     * @return \yii\base\Behavior|AttributeInterface|boolean
     */
    public function hasVirtualAttribute($name, $type = null)
    {
        foreach ($this->getBehaviors() as $behavior) {
            if (
                (null === $type || $behavior instanceof $type) &&
                $behavior instanceof AttributeInterface &&
                $behavior->hasAttribute($name)
            ) {
                return $behavior;
            }
        }
        return false;
    }

    /**
     * Returns the named attribute value.
     * If this record is the result of a query and the attribute is not loaded,
     * `null` will be returned.
     * @param string $name the attribute name
     * @return mixed the attribute value. `null` if the attribute is not set or does not exist.
     * @see hasAttribute()
     */
    public function getAttribute($name)
    {
        if ($this->hasAttribute($name)) {
            return parent::getAttribute($name);
        }

        if ($behavior = $this->hasVirtualAttribute($name)) {
            return $behavior->getAttribute($name);
        }

        return null;
    }

    /**
     * Returns the old value of the named attribute.
     * If this record is the result of a query and the attribute is not loaded,
     * `null` will be returned.
     * @param string $name the attribute name
     * @return mixed the old attribute value. `null` if the attribute is not loaded before
     * or does not exist.
     * @see hasAttribute()
     */
    public function getOldAttribute($name)
    {
        if ($this->hasAttribute($name)) {
            return parent::getOldAttribute($name);
        }

        /** @var OldAttributeInterface $behavior */
        if ($behavior = $this->hasVirtualAttribute($name, OldAttributeInterface::class)) {
            return $behavior->getOldAttribute($name);
        }

        return null;
    }

    /**
     * Returns a value indicating whether the named attribute has been changed.
     * @param string $name the name of the attribute.
     * @param bool $identical whether the comparison of new and old value is made for
     * identical values using `===`, defaults to `true`. Otherwise `==` is used for comparison.
     * @return bool whether the attribute has been changed
     */
    public function isAttributeChanged($name, $identical = true)
    {
        if (parent::isAttributeChanged($name, $identical)) {
            return true;
        }

        /** @var AttributeChangeInterface $behavior */
        if ($behavior = $this->hasVirtualAttribute($name, AttributeChangeInterface::class)) {
            return $behavior->isAttributeChanged($name, $identical);
        }

        return false;
    }
}