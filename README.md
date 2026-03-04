# optigov/fitconnect

PHP package for sending ZBP (Zentrales Buergerpostfach) messages and state updates via the [FIT-Connect Submission API v2](https://docs.fitko.de/fit-connect/).

Other submission types are not implemented yet.

## Requirements

- PHP 8.5+
- FIT-Connect API credentials
- RSA private key and ZBP certificate for signing and encryption

## Installation

```bash
composer require optigov/fitconnect
```

## Configuration

Create a typed config object with your credentials and endpoints or use one of the predefined endpoint sets:

```php
use OptiGov\FitConnect\Config\FitConnectConfig;
use OptiGov\FitConnect\Config\Endpoints;

$config = new FitConnectConfig(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    endpoints: Endpoints::test(),
    // Optional — only needed for ZBP operations (must provide all three or none):
    zbpDestinationId: 'your-destination-uuid',
    zbpSigningKey: file_get_contents('/path/to/key.pem'),
    zbpCertificate: file_get_contents('/path/to/cert.cer'),
);
```

## Setup

```php
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\Client\SenderClient;
use OptiGov\FitConnect\Client\ZbpClient;

$senderClient = new SenderClient($config, new Encryptor());

// For ZBP operations (requires zbpSigningKey + zbpCertificate in config):
$zbpClient = new ZbpClient($senderClient, $config);
```

## Usage

The package provides two equivalent APIs — a **fluent builder** API and a **DTO** API. Both follow the same code path.

### Sending a message (fluent builder)

```php
$result = $zbpClient->message()
    ->to('e0e02494-eca2-4a6b-9320-fc527747878c')
    ->from('My Service Portal')
    ->title('Your application has been received')
    ->content('We received your application on ...')
    ->service('My Service Portal')
    ->applicationId('9ef6c096-131c-4e0e-895a-3503b80775ec')
    ->send();

echo $result->submissionId;
echo $result->caseId;
echo $result->destinationId;
```

### Sending a message with attachments

```php
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;

$result = $zbpClient->message()
    ->to('e0e02494-eca2-4a6b-9320-fc527747878c')
    ->from('My Service Portal')
    ->title('Your application has been received')
    ->content('Please find your documents attached.')
    ->service('My Service Portal')
    ->attach(new Attachment('invoice.pdf', file_get_contents('/path/to/invoice.pdf'), 'application/pdf'))
    ->attach(new Attachment('receipt.pdf', file_get_contents('/path/to/receipt.pdf'), 'application/pdf'))
    ->send();
```

### Sending a message (DTO API)

```php
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\Enums\StorkQaaLevel;

$message = new ZbpMessage(
    mailboxUuid: 'e0e02494-eca2-4a6b-9320-fc527747878c',
    sender: 'My Service Portal',
    title: 'Your application has been received',
    content: 'We received your application on ...',
    service: 'My Service Portal',
    storkQaaLevel: StorkQaaLevel::BASIC,
    applicationId: '9ef6c096-131c-4e0e-895a-3503b80775ec',
    attachments: [
        new Attachment('invoice.pdf', file_get_contents('/path/to/invoice.pdf'), 'application/pdf'),
    ],
);

$result = $zbpClient->sendMessage($message);
```

### Sending a state update

```php
use OptiGov\FitConnect\Enums\ZbpSubmissionState;

$result = $zbpClient->state()
    ->applicationId('9ef6c096-131c-4e0e-895a-3503b80775ec')
    ->status(ZbpSubmissionState::PROCESSING)
    ->publicServiceName('Building Permit Application')
    ->senderName('Building Authority')
    ->statusDetails('Under review')
    ->additionalInformation('Additional information')
    ->send();
```

### Sending a state update (DTO API)

```php
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;

$state = new ZbpState(
    applicationId: '9ef6c096-131c-4e0e-895a-3503b80775ec',
    status: ZbpSubmissionState::PROCESSING,
    publicServiceName: 'Building Permit Application',
    senderName: 'Building Authority',
);

$result = $zbpClient->sendState($state);
```

### Checking submission status

```php
$status = $senderClient->getLastSubmissionEventLog($submissionId);

echo $status->state->value;
echo $status->issuer;
echo $status->issuedAt;
```

### Getting destination info

```php
$destination = $senderClient->getDestination($destinationId);

echo $destination->name;
echo $destination->status;
echo $destination->metadataVersions;
```

## Message builder options

| Method | Required | Description |
|--------|----------|-------------|
| `to(string $mailboxUuid)` | Yes | Recipient mailbox UUID |
| `from(string $sender)` | Yes | Sender name (1-255 chars) |
| `title(string $title)` | Yes | Message title (1-1024 chars) |
| `content(string $content)` | Yes | Message body |
| `service(string $service)` | Yes | Service name (1-255 chars) |
| `storkQaaLevel(StorkQaaLevel $level)` | No | Authentication level (default: `BASIC`) |
| `applicationId(string $uuid)` | No | Application UUID |
| `retrievalConfirmationAddress(string $addr)` | No | Retrieval confirmation address (max 320 chars) |
| `replyAddress(string $addr)` | No | Reply address (max 320 chars) |
| `reference(string $ref)` | No | Reference string (max 255 chars) |
| `senderUrl(string $url)` | No | Sender URL (max 255 chars) |
| `attach(Attachment $attachment)` | No | Add attachment (chainable) |

## State builder options

| Method | Required | Description |
|--------|----------|-------------|
| `applicationId(string $uuid)` | Yes | Application UUID |
| `status(ZbpSubmissionState $state)` | Yes | Submission state |
| `publicServiceName(string $name)` | Yes | Public service name (1-100 chars) |
| `senderName(string $name)` | Yes | Sender name (1-100 chars) |
| `statusDetails(string $details)` | No | Status details (max 50 chars) |
| `additionalInformation(string $info)` | No | Additional info (max 100 chars) |
| `reference(string $ref)` | No | Reference (max 50 chars) |
| `createdDate(string $datetime)` | No | ISO 8601 datetime (defaults to now) |
