<?php

declare(strict_types=1);

final readonly class PossibleCheaterRuleTuple
{
    public function __construct(
        private string $npCommunicationId,
        private int $orderId,
        private ?string $dateOperator,
        private ?string $dateValue,
    ) {
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getDateOperator(): ?string
    {
        return $this->dateOperator;
    }

    public function getDateValue(): ?string
    {
        return $this->dateValue;
    }

    public function toUnionSelect(): string
    {
        $npCommunicationId = $this->quote($this->npCommunicationId);
        $orderId = $this->orderId;
        $dateOperator = $this->dateOperator !== null ? $this->quote($this->dateOperator) : 'NULL';
        $dateValue = $this->dateValue !== null ? $this->quote($this->dateValue) : 'NULL';

        return 'SELECT '
            . $npCommunicationId . ' AS np_communication_id, '
            . $orderId . ' AS order_id, '
            . $dateOperator . ' AS date_operator, '
            . $dateValue . ' AS date_value';
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
