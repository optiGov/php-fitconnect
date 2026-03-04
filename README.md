# optigov/fitconnect

Laravel package for sending ZBP (Zentrales Buergerpostfach) messages and state updates via the [FIT-Connect Submission API v2](https://docs.fitko.de/fit-connect/).

Other submission type are not implemented yet.

## Requirements

- PHP 8.5+
- Laravel 12
- FIT-Connect API credentials
- RSA private key and ZBP certificate for signing and encryption

## Installation

```bash
composer require optigov/fitconnect
```

The service provider is auto-discovered.

## Configuration

Add the following to your `.env`:

```env
FITCONNECT_CLIENT_ID=your-client-id
FITCONNECT_CLIENT_SECRET=your-client-secret
FITCONNECT_DESTINATION_ID=your-destination-uuid
FITCONNECT_PRIVATE_KEY_PATH=/path/to/key.pem
FITCONNECT_CERTIFICATE_PATH=/path/to/cert.cer
```

### Environments

The config file (`config/fitconnect.php`) contains endpoint blocks for `test`, `stage`, and `prod`. 

Activate the desired environment by uncommenting the appropriate block and commenting out the others:

```php
'endpoints' => [
    // test (fitko.dev)
    'token'       => 'https://auth-testing.fit-connect.fitko.dev/token',
    'submission'  => 'https://test.fit-connect.fitko.dev/submission-api',
    'destination' => 'https://test.fit-connect.fitko.dev/destination-api',
    'routing'     => 'https://routing-api-testing.fit-connect.fitko.dev',

    // stage (fitko.net) — uncomment to use
    // 'token'       => 'https://auth-refz.fit-connect.fitko.net/token',
    // ...

    // prod (fitko.net) — uncomment to use
    // 'token'       => 'https://auth-prod.fit-connect.fitko.net/token',
    // ...
],
```

Publish the config first if you haven't already:

```bash
php artisan vendor:publish --tag=fitconnect-config
```

### Metadata version

```env
FITCONNECT_METADATA_VERSION=2.1.0
```

The metadata schema version defaults to `2.1.0` and is automatically negotiated with the destination's supported versions.

## Usage

The package provides two equivalent APIs — a **fluent builder** API and a **DTO** API. Both follow the same code path.

### Sending a message (fluent builder)

```php
use OptiGov\FitConnect\Facades\Zbp;

$result = Zbp::message()
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
use OptiGov\FitConnect\Facades\Zbp;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;

$result = Zbp::message()
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
use OptiGov\FitConnect\Facades\Zbp;
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

$result = Zbp::sendMessage($message);
```

### Sending a state update

```php
use OptiGov\FitConnect\Facades\Zbp;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;

$result = Zbp::state()
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
use OptiGov\FitConnect\Facades\Zbp;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;

$state = new ZbpState(
    applicationId: '9ef6c096-131c-4e0e-895a-3503b80775ec',
    status: ZbpSubmissionState::PROCESSING,
    publicServiceName: 'Building Permit Application',
    senderName: 'Building Authority',
);

$result = Zbp::sendState($state);
```

### Checking submission status

```php
use OptiGov\FitConnect\Facades\FitConnect;

$status = FitConnect::getLastSubmissionEventLog($submissionId);

echo $status->state->value;
echo $status->issuer;
echo $status->issuedAt;
```

### Getting destination info

```php
use OptiGov\FitConnect\Facades\FitConnect;

$destination = FitConnect::getDestination($destinationId);

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
| `attach(string $filename, string $content, string $mimeType)` | No | Add attachment (chainable) |

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

