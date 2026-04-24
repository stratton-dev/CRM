<?php

namespace App\Services\Keycloak;

class SyncReport
{
    public function __construct(
        public string $status,
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $errors = 0,
        public array $messages = []
    ) {
    }

    public static function disabled(): self
    {
        return new self('disabled');
    }

    public static function locked(): self
    {
        return new self('locked');
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'messages' => $this->messages,
        ];
    }
}
