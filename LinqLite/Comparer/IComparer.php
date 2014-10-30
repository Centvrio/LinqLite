<?php
namespace LinqLite\Comparer;

/**
 * Interface IComparer
 * @package Linq
 * @subpackage Comparer
 */
interface IComparer
{
    /**
     * @param ComparerParam $x
     * @param ComparerParam $y
     * @return bool
     */
    public function equals(ComparerParam $x, ComparerParam $y);
}