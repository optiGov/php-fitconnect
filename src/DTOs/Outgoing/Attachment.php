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

    public static function fromPath(string $path, ?string $mimeType = null): self
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File does not exist: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \InvalidArgumentException("Could not read file: {$path}");
        }

        return new self(
            filename: basename($path),
            content: $content,
            mimeType: $mimeType ?? (mime_content_type($path) ?: 'application/octet-stream'),
        );
    }

    public static function fromString(string $content, string $filename, string $mimeType = 'application/octet-stream'): self
    {
        return new self(
            filename: $filename,
            content: $content,
            mimeType: $mimeType,
        );
    }
}
