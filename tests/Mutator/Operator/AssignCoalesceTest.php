<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017-2019, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\Tests\Mutator\Operator;

use Infection\Tests\Mutator\AbstractMutatorTestCase;

/**
 * @internal
 */
final class AssignCoalesceTest extends AbstractMutatorTestCase
{
    /**
     * @dataProvider provideMutationCases
     */
    public function test_mutator($input, $expected = null): void
    {
        $this->doTest($input, $expected);
    }

    public function provideMutationCases(): \Generator
    {
        yield 'Mutate coalesce when right part is a scalar value' => [
            <<<'PHP'
<?php

$a['value'] ??= 'otherValue';
PHP
            ,
            <<<'PHP'
<?php

$a['value'] = 'otherValue';
PHP
        ];

        yield 'Mutate coalesce when right part is an expression' => [
            <<<'PHP'
<?php

$a['value'] ??= 'other' . ' Value';
PHP
            ,
            <<<'PHP'
<?php

$a['value'] = 'other' . ' Value';
PHP
        ];

        yield 'Mutate coalesce when right part is a variable' => [
            <<<'PHP'
<?php

$a['value'] ??= $var;
PHP
            ,
            <<<'PHP'
<?php

$a['value'] = $var;
PHP
        ];

        yield 'Does not mutate coalesce binary operator' => [
            <<<'PHP'
<?php

$a['value'] = $foo ?? $bar;
PHP
            ,
        ];
    }
}
