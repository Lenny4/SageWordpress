<?php

namespace App\class;

final class SageExpectedOption
{
    private ?string $currentOptionValue;

    public function __construct(
        private string $optionName,
        private string $optionValue,
        private string $trans,
        private string $description,
    )
    {
    }

    public function getOptionName(): string
    {
        return $this->optionName;
    }

    public function setOptionName(string $optionName): self
    {
        $this->optionName = $optionName;
        return $this;
    }

    public function getOptionValue(): string
    {
        return $this->optionValue;
    }

    public function setOptionValue(string $optionValue): self
    {
        $this->optionValue = $optionValue;
        return $this;
    }

    public function getTrans(): string
    {
        return $this->trans;
    }

    public function setTrans(string $trans): self
    {
        $this->trans = $trans;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCurrentOptionValue(): ?string
    {
        return $this->currentOptionValue;
    }

    public function setCurrentOptionValue(?string $currentOptionValue): self
    {
        $this->currentOptionValue = $currentOptionValue;
        return $this;
    }
}
