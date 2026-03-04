<?php

namespace OptiGov\FitConnect\Tests\Unit;

use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use PHPUnit\Framework\TestCase;

class AttachmentTest extends TestCase
{
    public function test_from_string_creates_attachment(): void
    {
        $attachment = Attachment::fromString('hello world', 'test.txt', 'text/plain');

        $this->assertSame('test.txt', $attachment->filename);
        $this->assertSame('hello world', $attachment->content);
        $this->assertSame('text/plain', $attachment->mimeType);
        $this->assertNotEmpty($attachment->id);
    }

    public function test_from_string_uses_default_mime_type(): void
    {
        $attachment = Attachment::fromString('data', 'file.bin');

        $this->assertSame('application/octet-stream', $attachment->mimeType);
    }

    public function test_from_string_rejects_empty_content(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Attachment::fromString('', 'test.txt');
    }

    public function test_from_path_creates_attachment(): void
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

    public function test_from_path_detects_mime_type(): void
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

    public function test_from_path_throws_for_missing_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File does not exist');
        Attachment::fromPath('/nonexistent/path/file.txt');
    }
}
