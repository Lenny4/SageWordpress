<?php

namespace App\resources;

use Closure;

class ImportConditionDto
{
    private string $field;
    private array|string|bool|int $value;
    private string $condition;
    private Closure $message;

    public function __construct(string $field, array|string|bool|int $value, string $condition, Closure $message)
    {
        $this->field = $field;
        $this->value = $value;
        $this->condition = $condition;
        $this->message = $message;
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
