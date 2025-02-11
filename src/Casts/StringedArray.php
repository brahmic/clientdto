<?php

namespace Brahmic\ClientDTO\Casts;

class StringedArray
{

    private function arrayToString($array, bool $trim = false): string
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Рекурсивно обрабатываем вложенные массивы
                $result[] = $this->arrayToString($value);
            } else {
                // Обрабатываем простые значения
                $result[] = is_string($value) ? "'$value'" : $value;
            }
        }
        return '[' . implode(',' . $trim ? '' : ' ', $result) . ']';
    }
}