<?php

namespace App\class\Dto;

final class ArgumentSelectionSetDto
{
    public function __construct(
        private array $selectionSet,
        private array $arguments,
    )
    {
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getSelectionSet(): array
    {
        return $this->selectionSet;
    }

    public function setSelectionSet(array $selectionSet): self
    {
        $this->selectionSet = $selectionSet;
        return $this;
    }
}
