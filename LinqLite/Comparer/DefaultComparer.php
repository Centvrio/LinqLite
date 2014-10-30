<?php
namespace LinqLite\Comparer;


/**
 * Class DefaultComparer
 *
 * @package LinqLite
 * @subpackage Comparer
 */
class DefaultComparer implements IComparer
{
    /**
     * Default equals method
     *
     * @param ComparerParam $x First element
     * @param ComparerParam $y Second element
     *
     * @return boolean
     */
    public function equals(ComparerParam $x, ComparerParam $y)
    {
        return $x->value == $y->value;
    }

}
