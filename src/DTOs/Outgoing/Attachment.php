<?php

namespace OptiGov\FitConnect\DTOs\Outgoing;

use OptiGov\FitConnect\Traits\ValidateHelper;

readonly class Attachment
{
    use ValidateHelper;

    public function __construct(
        public string $filename,
        public string $content,
        public string $mimeType,
    ) {
        self::assertLength($this->filename, 'filename', 1, 4000);
        if ($this->content === '') {
            throw new \InvalidArgumentException(static::class.": 'content' must not be empty");
        }
    }
}
