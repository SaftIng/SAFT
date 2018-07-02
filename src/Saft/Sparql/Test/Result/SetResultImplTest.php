<?php

/*
 * This file is part of Saft.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Natanael Arndt <arndt@informatik.uni-leipzig.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Saft\Sparql\Test\Result;

use Saft\Sparql\Result\SetResult;
use Saft\Sparql\Result\SetResultImpl;

class SetResultImplTest extends AbstractSetResultTest
{
    /**
     * @param \Iterator $list Default is []
     *
     * @return SetResult
     */
    public function getInstance($list = []): SetResult
    {
        return new SetResultImpl($list);
    }
}
