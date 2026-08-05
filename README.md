# Kipchak ClamAV Driver

A ClamAV (`clamd`) antivirus driver for the Kipchak API Development Kit.

Speaks the `clamd` `INSTREAM` protocol over TCP to scan in-memory byte blobs
without writing them to disk.

## Config

`config/kipchak.clamav.php` — one entry per named connection:

```php
return [
    'default' => [
        'host'    => env('CLAMAV_HOST', '127.0.0.1'),
        'port'    => (int) env('CLAMAV_PORT', '3310'),
        'timeout' => (float) env('CLAMAV_TIMEOUT_SECONDS', '30'),
    ],
];
```

## Boot

```php
use Kipchak\Driver\ClamAV\ClamAV;

ClamAV::initialise($container);
```

## Usage

```php
$result = ClamAV::get('default')->scan($bytes);
if (! $result->isClean()) {
    // $result->getSignature() e.g. "Eicar-Test-Signature"
}
```

`scan()` throws `Kipchak\Driver\ClamAV\Exception\ConnectionException` when
`clamd` cannot be reached — the caller decides whether that fails open or closed.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The tests override the socket exchange with canned `clamd` responses, so no
running daemon is required. Live coverage uses the EICAR test signature.
