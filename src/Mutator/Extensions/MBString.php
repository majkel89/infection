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

namespace Infection\Mutator\Extensions;

use Generator;
use Infection\Mutator\Util\Mutator;
use Infection\Mutator\Util\MutatorConfig;
use PhpParser\Node;

/**
 * @internal
 */
final class MBString extends Mutator
{
    private $converters;

    public function __construct(MutatorConfig $config)
    {
        parent::__construct($config);

        $settings = $this->getSettings();

        $this->setupConverters($settings);
    }

    /**
     * @return Node|Node[]|Generator
     */
    public function mutate(Node $node)
    {
        yield from $this->converters[$this->getFunctionName($node)]($node);
    }

    protected function mutatesNode(Node $node): bool
    {
        return isset($this->converters[$this->getFunctionName($node)]);
    }

    private function setupConverters(array $functionsMap): void
    {
        $converters = [
            'mb_chr' => $this->mapFunctionAndRemoveExtraArgs('chr', 1),
            'mb_ord' => $this->mapFunctionAndRemoveExtraArgs('ord', 1),
            'mb_parse_str' => $this->mapFunction('parse_str'),
            'mb_send_mail' => $this->mapFunction('mail'),
            'mb_strcut' => $this->mapFunctionAndRemoveExtraArgs('substr', 3),
            'mb_stripos' => $this->mapFunctionAndRemoveExtraArgs('stripos', 3),
            'mb_stristr' => $this->mapFunctionAndRemoveExtraArgs('stristr', 3),
            'mb_strlen' => $this->mapFunctionAndRemoveExtraArgs('strlen', 1),
            'mb_strpos' => $this->mapFunctionAndRemoveExtraArgs('strpos', 3),
            'mb_strrchr' => $this->mapFunctionAndRemoveExtraArgs('strrchr', 2),
            'mb_strripos' => $this->mapFunctionAndRemoveExtraArgs('strripos', 3),
            'mb_strrpos' => $this->mapFunctionAndRemoveExtraArgs('strrpos', 3),
            'mb_strstr' => $this->mapFunctionAndRemoveExtraArgs('strstr', 3),
            'mb_strtolower' => $this->mapFunctionAndRemoveExtraArgs('strtolower', 1),
            'mb_strtoupper' => $this->mapFunctionAndRemoveExtraArgs('strtoupper', 1),
            'mb_substr_count' => $this->mapFunctionAndRemoveExtraArgs('substr_count', 2),
            'mb_substr' => $this->mapFunctionAndRemoveExtraArgs('substr', 3),
            'mb_convert_case' => $this->mapConvertCase(),
        ];

        $functionsToRemove = \array_filter($functionsMap, static function ($isOn) {
            return !$isOn;
        });

        $this->converters = \array_diff_key($converters, $functionsToRemove);
    }

    private function mapFunction(string $newFunctionName): callable
    {
        return function (Node\Expr\FuncCall $node) use ($newFunctionName): Generator {
            yield $this->mapFunctionCall($node, $newFunctionName, $node->args);
        };
    }

    private function mapFunctionAndRemoveExtraArgs(string $newFunctionName, int $argsAtMost): callable
    {
        return function (Node\Expr\FuncCall $node) use ($newFunctionName, $argsAtMost): Generator {
            yield $this->mapFunctionCall($node, $newFunctionName, \array_slice($node->args, 0, $argsAtMost));
        };
    }

    private function mapConvertCase(): callable
    {
        return function (Node\Expr\FuncCall $node): Generator {
            $modeValue = $this->getConvertCaseModeValue($node);

            if ($modeValue === null) {
                return;
            }

            $functionName = $this->getConvertCaseFunctionName($modeValue);

            if ($functionName === null) {
                return;
            }

            yield $this->mapFunctionCall($node, $functionName, [$node->args[0]]);
        };
    }

    private function getConvertCaseModeValue(Node\Expr\FuncCall $node): ?int
    {
        if (\count($node->args) < 2) {
            return null;
        }

        $mode = $node->args[1]->value;

        if ($mode instanceof Node\Scalar\LNumber) {
            return $mode->value;
        }

        if ($mode instanceof Node\Expr\ConstFetch) {
            return \constant($mode->name->toString());
        }

        return null;
    }

    private function getConvertCaseFunctionName(int $mode): ?string
    {
        if ($this->isInMbCaseMode($mode, 'MB_CASE_UPPER', 'MB_CASE_UPPER_SIMPLE')) {
            return 'strtoupper';
        }

        if ($this->isInMbCaseMode($mode, 'MB_CASE_LOWER', 'MB_CASE_LOWER_SIMPLE', 'MB_CASE_FOLD', 'MB_CASE_FOLD_SIMPLE')) {
            return 'strtolower';
        }

        if ($this->isInMbCaseMode($mode, 'MB_CASE_TITLE', 'MB_CASE_TITLE_SIMPLE')) {
            return 'ucwords';
        }

        return null;
    }

    private function isInMbCaseMode(int $mode, string ...$cases): bool
    {
        foreach ($cases as $constant) {
            if (\defined($constant) && \constant($constant) === $mode) {
                return true;
            }
        }

        return false;
    }

    private function getFunctionName(Node $node): ?string
    {
        if (!$node instanceof Node\Expr\FuncCall || !$node->name instanceof Node\Name) {
            return null;
        }

        return $node->name->toLowerString();
    }

    private function mapFunctionCall(Node\Expr\FuncCall $node, string $newFuncName, array $args): Node\Expr\FuncCall
    {
        return new Node\Expr\FuncCall(
            new Node\Name($newFuncName, $node->name->getAttributes()),
            $args,
            $node->getAttributes()
        );
    }
}
