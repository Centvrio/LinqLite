<?php
namespace LinqLite\Comparer;

/**
 * Interface IComparer
 *
 * @package    Linq
 * @subpackage Comparer
 */
interface IComparer
{
    /**
     * Equals method
     *
     * @param ComparerParam $x First element.
     * @param ComparerParam $y Second element.
     *
     * @return boolean
     */
    public function equals(ComparerParam $x, ComparerParam $y);
}