<?php
namespace LinqLite\Comparer;


class DefaultComparer implements IComparer
{
    /**
     * @param ComparerParam $x
     * @param ComparerParam $y
     * @return bool
     */
    public function equals(ComparerParam $x, ComparerParam $y)
    {
        return $x->value=$y->value;
    }

} 