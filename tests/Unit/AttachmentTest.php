<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests\Unit;

use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class AttachmentTest extends TestCase
{
    public function testFromStringCreatesAttachment(): void
    {
        $attachment = Attachment::fromString('hello world', 'test.txt', 'text/plain');

        $this->assertSame('test.txt', $attachment->filename);
        $this->assertSame('hello world', $attachment->content);
        $this->assertSame('text/plain', $attachment->mimeType);
        $this->assertNotEmpty($attachment->id);
    }

    public function testFromStringUsesDefaultMimeType(): void
    {
        $attachment = Attachment::fromString('data', 'file.bin');

        $this->assertSame('application/octet-stream', $attachment->mimeType);
    }

    public function testFromStringRejectsEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Attachment::fromString('', 'test.txt');
    }

    public function testFromPathCreatesAttachment(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'attach_test_');
        file_put_contents($tmpFile, 'file content');

        try {
            $attachment = Attachment::fromPath($tmpFile, 'text/plain');

            $this->assertSame(basename($tmpFile), $attachment->filename);
            $this->assertSame('file content', $attachment->content);
            $this->assertSame('text/plain', $attachment->mimeType);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testFromPathDetectsMimeType(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'attach_test_').'.txt';
        file_put_contents($tmpFile, 'plain text');

        try {
            $attachment = Attachment::fromPath($tmpFile);

            $this->assertSame('plain text', $attachment->content);
            // mime_content_type should detect text/plain
            $this->assertSame('text/plain', $attachment->mimeType);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testFromPathThrowsForMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File does not exist');
        Attachment::fromPath('/nonexistent/path/file.txt');
    }
}
