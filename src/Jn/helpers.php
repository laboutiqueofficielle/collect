<?php

namespace Jn;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Tightenco\Collect\Support\Arr;
use Jn\Collection;

function collect($value = null)
{
    return new Collection($value);
}
/**
 * Get an item from an array or object using "dot" notation.
 *
 * @param  mixed   $target
 * @param  string|array  $key
 * @param  mixed   $default
 * @return mixed
 */
function data_get($target, $key, $default = null)
{
    if (is_null($key)) {
        return $target;
    }

    $key = is_array($key) ? $key : explode('.', $key);

    while (($segment = array_shift($key)) !== null) {
        if ($segment === '*') {
            if ($target instanceof Collection) {
                $target = $target->all();
            } elseif (! is_array($target)) {
                return value($default);
            }

            $result = Arr::pluck($target, $key);

            return in_array('*', $key) ? Arr::collapse($result) : $result;
        }

        if (Arr::accessible($target) && Arr::exists($target, $segment)) {
            $target = $target[$segment];
        } elseif (is_object($target)) {
            return PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor()->getValue($target, $segment);
        } else {
            return value($default);
        }
    }

    return $target;
}
