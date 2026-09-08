<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class TransactionRequest extends FormRequest
{
    protected string $permission;

    public function authorize(): bool
    {
        return $this->user()?->can($this->permission) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['_key' => $this->header('Idempotency-Key')]);
    }

    final public function rules(): array
    {
        return ['_key' => ['required', 'string', 'min:8', 'max:128'], ...$this->fields()];
    }

    abstract protected function fields(): array;

    protected function money(bool $positive = false): array
    {
        return ['required', 'string', 'numeric', 'decimal:0,2', $positive ? 'gt:0' : 'gte:0', 'max:999999999999.99'];
    }

    protected function quantity(): array
    {
        return ['required', 'string', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'];
    }
}
