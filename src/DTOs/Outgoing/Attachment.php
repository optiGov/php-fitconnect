<?php

namespace OptiGov\FitConnect\DTOs\Outgoing;

use OptiGov\FitConnect\Traits\ValidateHelper;
use Symfony\Component\Uid\Uuid;

readonly class Attachment
{
    use ValidateHelper;

    public string $id;

    public function __construct(
        public string $filename,
        public string $content,
        public string $mimeType,
        string $id = '',
    ) {
        $this->id = $id !== '' ? $id : Uuid::v4()->toString();
        self::assertLength($this->filename, 'filename', 1, 4000);
        if ($this->content === '') {
            throw new \InvalidArgumentException(static::class.": 'content' must not be empty");
        }
    }
}
