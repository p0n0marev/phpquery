<?php

namespace P0n0marev\PhpQuery;

interface PhpQueryInterface
{
    public function find(string $selector): self;

    public function first(): self;

    public function last(): self;

    public function length(): int;

    public function count(): int;
}