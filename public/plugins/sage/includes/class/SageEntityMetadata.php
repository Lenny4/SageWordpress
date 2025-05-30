<?php

namespace App\class;

final class SageEntityMetadata
{
    /**
     * @param callable $function
     */
    public function __construct(
        private string $field,
        private        $value,
        private bool   $showInOptions = false,
    )
    {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getShowInOptions(): bool
    {
        return $this->showInOptions;
    }

    public function setShowInOptions(bool $showInOptions): self
    {
        $this->showInOptions = $showInOptions;
        return $this;
    }
}
