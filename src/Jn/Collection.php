<?php

namespace Jn;

use ArrayIterator;
use function Jn\data_get;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Tightenco\Collect\Support\Arr;
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
     * Get a value retrieving callback.
     *
     * @param  string  $value
     * @return callable
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return data_get($item, $value);
        };
    }

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
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return bool
     */
    public function containsStrict($key, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return data_get($item, $key) === $value;
            });
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->items, true);
    }

    /**
     * Get the values of a given key.
     *
     * @param  string|array  $value
     * @param  string|null  $key
     * @return \Tightenco\Collect\Support\Collection
     */
    public function pluck($value, $key = null)
    {
        return new static($this->ArrPluck($this->items, $value, $key));
    }

    /**
     * Pluck an array of values from an array.
     *
     * @param  array  $array
     * @param  string|array  $value
     * @param  string|array|null  $key
     * @return array
     */
    public static function ArrPluck($array, $value, $key = null)
    {
        $results = [];

        [$value, $key] = self::explodePluckParameters($value, $key);

        foreach ($array as $item) {
            $itemValue = data_get($item, $value);
            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = \Jn\data_get($item, $key);

                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string) $itemKey;
                }

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param  string|array  $value
     * @param  string|array|null  $key
     * @return array
     */
    public static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * Get an operator checker callback.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operatorForWhere($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            $value = true;

            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;

            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });

            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':  return $retrieved == $value;
                case '!=':
                case '<>':  return $retrieved != $value;
                case '<':   return $retrieved < $value;
                case '>':   return $retrieved > $value;
                case '<=':  return $retrieved <= $value;
                case '>=':  return $retrieved >= $value;
                case '===': return $retrieved === $value;
                case '!==': return $retrieved !== $value;
            }
        };
    }



    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param  string  $key
     * @param  array  $values
     * @return \Tightenco\Collect\Support\Collection
     */
    public function whereNotBetween($key, $values)
    {
        return $this->filter(function ($item) use ($key, $values) {
            return data_get($item, $key) < reset($values) || data_get($item, $key) > end($values);
        });
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return static
     */
    public function whereNotIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->reject(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return \Tightenco\Collect\Support\Collection
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
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