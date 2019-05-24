<?php

namespace Jn;

use ArrayIterator;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tightenco\Collect\Support\Collection as SupportCollection;

/**
 * Class Collection
 *
 * Custom implementation of Laravel Collection
 *
 * @author Xavier Dubois <juyn89@gmail.com>
 */
class Collection extends SupportCollection
{
    /**
     * Get a sorted iterator on a field.
     *
     * @param string $field
     *
     * @return ArrayIterator
     */
    public function getSortedIteratorOn(string $field): ArrayIterator
    {
        $accessor = $this->getAccessor();
        $iterator = $this->getIterator();
        $iterator->uasort(function ($first, $second) use ($accessor, $field) {
            $firstValue = $accessor->getValue($first, $field);
            $secondValue = $accessor->getValue($second, $field);
            if ($firstValue === $secondValue) {
                return 0;
            }

            return ($firstValue < $secondValue ? -1 : 1);
        });
        $list = iterator_to_array($iterator, false);

        return new ArrayIterator($list);
    }

    /**
     * Return a new collection with sorted result.
     *
     * @param string $field
     *
     * @return Collection
     */
    public function getSortedCollection(?string $field = null): Collection
    {
        if (null !== $field) {
            return new self($this->getSortedIteratorOn($field)->getArrayCopy());
        }

        $arr = $this->toArray();
        sort($arr);

        return new self($arr);
    }

    /**
     * Return a sanitized ArrayCollection without any null values
     *
     * @param null|string $field
     *
     * @return Collection
     */
    public function withoutNull(?string $field = null): Collection
    {
        return $this->filter(function ($element) use ($field) {
            if (null !== $field) {
                $value = $this->valueRetriever($field);
            } else {
                $value = $element;
            }

            return !empty($value);
        });
    }

    /**
     * Extract one property from an ArrayCollection of object into a new ArrayCollection
     *
     * @param string $field
     *
     * @return Collection
     */
    public function extract(string $field): Collection
    {
        return $this
            ->withoutNull($field)
            ->map(function ($element) use ($field) {
                return $this->getAccessor()->getValue($element, $field);
            })
            ->flatten()
            ->getSortedCollection();
    }

    /**
     * Filter a collection by its keys
     *
     * @param array $keys
     *
     * @return Collection
     */
    public function filterByKey(array $keys): Collection
    {
        $values = new Collection($this->partition(function ($key) use ($keys) {
            return in_array($key, $keys);
        }));

        return $values[0];
    }

    /**
     * Extract a Collection and return an Collection built with the data
     *
     * @param mixed  $collection
     * @param string $field
     *
     * @return Collection
     */
    public static function extractCollection($collection, string $field = 'collection'): Collection
    {
        $data = PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor()->getValue($collection, $field);

        return new self($data);
    }

    /**
     * Get Property Accessor to works with unknown objects
     *
     * @return PropertyAccessorInterface
     */
    private function getAccessor(): PropertyAccessorInterface
    {
        return PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor();
    }
}