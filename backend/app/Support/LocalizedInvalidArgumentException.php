<?php

namespace App\Support;

use InvalidArgumentException;

class LocalizedInvalidArgumentException extends InvalidArgumentException
{
    public function __construct(
        string $messageEn,
        public readonly string $messageFa,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageEn, $code, $previous);
    }

    /**
     * @return array{message:string,message_en:string,message_fa:string,context:array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'message_en' => $this->getMessage(),
            'message_fa' => $this->messageFa,
            'context' => $this->context,
        ];
    }
}
