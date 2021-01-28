<?php
namespace ubfr\c5tools;

trait Helpers
{

    protected function inArrayLax($needle, $haystack)
    {
        $needle = $this->lax($needle);
        foreach ($haystack as $value) {
            if ($needle === $this->lax($value)) {
                return $value;
            }
        }
        return false;
    }

    protected function lax($string)
    {
        return strtolower(preg_replace('/[\s"“”\'_-]/', '', $string));
    }

    protected function isNonEmptyArray($position, $element, $value, $optional = true)
    {
        if (! is_array($value)) {
            $message = "{$element} must be an array";
            $this->checkResult->addCriticalError($message, $message, $position, $element);
            return false;
        }
        if (empty($value)) {
            $message = "{$element} must not be empty";
            $this->checkResult->addError($message, $message, $position, $element,
                ($optional ? 'optional elements without a value must be omitted' : null));
            return false;
        }
        return true;
    }

    protected function endsWith($needle, $haystack)
    {
        $needleLength = strlen($needle);
        $haystackLength = strlen($haystack);
        if ($haystackLength < $needleLength) {
            return false;
        }
        return (substr($haystack, - $needleLength) === $needle);
    }

    protected function formatData($element, $value)
    {
        return "{$element} '{$value}'";
    }

    protected function getContext($element)
    {
        if (substr($element, 0, 10) === 'Component_') {
            return 'Component';
        }
        if (substr($element, 0, 7) === 'Parent_') {
            return 'Parent';
        }
        return 'Item';
    }

    protected function getElementName($element, $context)
    {
        if ($context === 'Item' || $this->getFormat() === self::FORMAT_JSON) {
            return $element;
        } else {
            return $context . '_' . $element;
        }
    }

    protected function getItemNameElement($context)
    {
        if ($context !== 'Item') {
            return 'Item_Name';
        }
        foreach ([
            'Item',
            'Title',
            'Database'
        ] as $element) {
            if (isset($this->elements[$element])) {
                return $element;
            }
        }
        return 'Platform';
    }

    protected function getIsbn13($isbn10)
    {
        $isbn10 = str_replace('-', '', $isbn10);
        if (! preg_match('/^[0-9]{9}[0-9xX]$/', $isbn10)) {
            throw new \Exception("System Error - getIsbn13: parameter {$isbn10} is no ISBN10");
        }
        $isbn13 = '978' . substr($isbn10, 0, 9);
        $checksum = 0;
        for ($c = 0; $c < 12; $c ++)
            $checksum += substr($isbn13, $c, 1) * (1 + 2 * ($c % 2));
        $checksum = 10 - ($checksum % 10);
        if ($checksum == 10)
            $checksum = 0;
        return $isbn13 . $checksum;
    }
}
