<?php

namespace HireHq\ConsoleIntegration\Data;

final readonly class ProductContext
{
    public function __construct(public string $environment, public string $product, public string $companyId) {}

    /** @return array{environment: string, product: string, company_id: string} */
    public function key(): array
    {
        return ['environment' => $this->environment, 'product' => $this->product, 'company_id' => $this->companyId];
    }
}
