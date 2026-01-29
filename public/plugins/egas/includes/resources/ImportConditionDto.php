<?php

namespace App\resources;

use Closure;

class ImportConditionDto
{
    public function __construct(private string $field, private array|string|bool|int $value, private string $condition, private Closure $message)
    {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): ImportConditionDto
    {
        $this->field = $field;
        return $this;
    }

    public function getValue(): int|bool|array|string
    {
        return $this->value;
    }

    public function setValue(int|bool|array|string $value): ImportConditionDto
    {
        $this->value = $value;
        return $this;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function setCondition(string $condition): ImportConditionDto
    {
        $this->condition = $condition;
        return $this;
    }

    public function getMessage(): Closure
    {
        return $this->message;
    }

    public function setMessage(Closure $message): ImportConditionDto
    {
        $this->message = $message;
        return $this;
    }
}
