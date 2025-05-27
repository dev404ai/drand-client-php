# Drand Client for PHP

[![Build Status](https://github.com/dev404ai/drand-client-php/actions/workflows/ci.yml/badge.svg)](https://github.com/dev404ai/drand-client-php/actions)
[![Psalm](https://img.shields.io/badge/psalm-passing-brightgreen?logo=php)](https://github.com/dev404ai/drand-client-php/actions?query=workflow%3ACI)
[![PHPStan](https://img.shields.io/badge/phpstan-passing-brightgreen?logo=php)](https://github.com/dev404ai/drand-client-php/actions?query=workflow%3ACI)
[![Code Style: PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue)](https://www.php-fig.org/psr/psr-12/)
[![Mutation Testing](https://img.shields.io/badge/mutation%20testing-infection-green)](https://github.com/dev404ai/drand-client-php/actions?query=workflow%3ACI)
[![Latest Stable Version](https://poser.pugx.org/dev404ai/drand-client-php/v/stable)](https://packagist.org/packages/dev404ai/drand-client-php)
[![Total Downloads](https://poser.pugx.org/dev404ai/drand-client-php/downloads)](https://packagist.org/packages/dev404ai/drand-client-php)
[![License](https://poser.pugx.org/dev404ai/drand-client-php/license)](https://packagist.org/packages/dev404ai/drand-client-php)
[![PHP Version](https://img.shields.io/packagist/php-v/dev404ai/drand-client-php)](https://packagist.org/packages/dev404ai/drand-client-php)
[![Last Commit](https://img.shields.io/github/last-commit/dev404ai/drand-client-php.svg)](https://github.com/dev404ai/drand-client-php/commits/main)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](https://makeapullrequest.com)

## About
A minimal-dependency PHP client for the [drand randomness beacon](https://drand.love), providing fast, verifiable, and unbiased public randomness. Supports all drand signature schemes, efficient BLS verification via libblst, and is suitable for blockchain, games, lotteries, and any application requiring cryptographically secure randomness. Easy to install, PSR-4 autoloaded, and fully tested with PHPUnit.

## Table of Contents

- [Quick Install](#quick-install)
- [Usage Examples](#usage-examples)
- [Supported Signature Schemes](#supported-signature-schemes)
- [Configuration Enums](#configuration-enums)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

A **minimal‑dependency** PHP client for the [drand randomness beacon](https://drand.love).

* Fast BLS‑signature verification via **libblst** (same core used by Ethereum 2).
* Supports all drand signature schemes: chained, unchained, G1, RFC9380, and BN254 (see below).
* No PECL modules — just enable **FFI**.
* Composer‑installable, PSR‑4, PHPUnit‑tested.

---

## Use Cases

This library can be used wherever **verifiable, unbiased, and public randomness** is required, including:

- **Random number generation (RNG)**: Use as a source of entropy for applications needing strong, unpredictable randomness.
- **Blockchain and smart contracts**: Integrate with on-chain or off-chain systems that require public randomness.
- **Games and competitions**: Provide fair shuffling, matchmaking, or prize draws.
- **Auditable random selection**: Any scenario where you need to prove to users that the randomness was not manipulated.
- **Betting and gambling platforms**: Ensure fair, auditable outcomes for games and bets.
- **Online lotteries and raffles**: Draw winners transparently and provably at random.

Drand randomness is **public, distributed, and cryptographically verifiable**, making it suitable for regulated and high-stakes environments.

---

## Supported Signature Schemes

This client supports all major drand signature schemes:

- `pedersen-bls-chained` (default, BLS12-381, chained)
- `pedersen-bls-unchained` (BLS12-381, unchained)
- `bls-unchained-on-g1` (BLS12-381, G1, legacy)
- `bls-unchained-g1-rfc9380` (BLS12-381, G1, RFC9380)
- `bn254-on-g1` (BN254, G1, experimental)

> **Note:**
> - For G1 and RFC9380 schemes, FFI and libblst are required.
> - The client automatically detects and verifies the correct scheme based on chain info.
> - BN254 support requires a separate backend (not included by default).

---

## Quick install

### 1. Install `libblst`

| Platform | Command |
|----------|---------|
| **Ubuntu / Debian** | `sudo apt install libblst-dev` |
| **Arch Linux** | `sudo pacman -S blst` |
| **Alpine Linux** | `apk add blst` |
| **macOS (Homebrew)** | `brew install blst` |
| **Windows** | Download **blst.dll** from the [releases](https://github.com/supranational/blst/releases) and copy it next to `php.exe` or into `%SystemRoot%\System32`. |

> **Important:** This project requires the [php-ffi](https://www.php.net/manual/en/book.ffi.php) extension. Make sure it is installed and enabled in your PHP environment.
>
> - On **Arch Linux**, install it with:
>   ```bash
>   sudo pacman -S php-ffi
>   ```
> - On other systems, ensure the `ffi` extension is enabled in your `php.ini` (see below).
> - You can check if FFI is enabled by running:
>   ```bash
>   php -m | grep ffi
>   ```
>   If you see `ffi` in the output, the extension is enabled.

<details>
<summary>Alternatively, build from source</summary>

```bash
git clone https://github.com/supranational/blst.git
cd blst
./build.sh shared                               # creates libblst.{so|dylib|dll}
sudo cp build/*/generic/libblst.* /usr/local/lib
sudo ldconfig                                   # Linux only
```
</details>

### 2. Enable FFI

```bash
# Permanent
echo "ffi.enable = true" | sudo tee /etc/php/conf.d/20-ffi.ini

# One‑off
php -dffi.enable=1 your_script.php
```

> **Reloading web SAPIs:**  
> CLI picks the change immediately. For PHP‑FPM / Apache reload the service:
>
> ```bash
> # find your FPM unit
> systemctl list-units 'php*-fpm*'
>
> # e.g. php8.2-fpm
> sudo systemctl reload php8.2-fpm
>
> # Apache with mod_php
> sudo systemctl reload apache2
> ```

### 3. Install the client via Composer

```bash
composer require dev404ai/drand-client-php
```

This pulls the latest tagged release and registers PSR‑4 autoloading.

> **Verify setup**
>
> ```bash
> php -m | grep ffi        # ffi
> php -r "new FFI(); echo 'FFI OK\n';"
> ```

---

## Usage Examples

### Basic Usage

```php
require 'vendor/autoload.php';

use Drand\Client\DrandClient;

// Create a client with default settings
$client = DrandClient::createDefault();

// Get the latest beacon (scheme is detected and verified automatically)
$beacon = $client->getLatestBeacon();
printf("Round %d: %s\n", $beacon->getRound(), $beacon->getRandomness());

// Get a specific round
$beacon = $client->getBeacon(12345);

// Get chain information
$chain = $client->getChain();
printf("Chain period: %d seconds\n", $chain->getPeriod());
printf("Signature scheme: %s\n", $chain->getSchemeID());
```

### Advanced Configuration

```php
// Create a client with custom options
$client = DrandClient::createDefault(
    'https://api.drand.sh',  // Base URL
    [
        // Disable signature verification (not recommended for production)
        'disableBeaconVerification' => false,
        
        // Disable response caching
        'noCache' => false,
        
        // Verify chain hash and public key
        'chainVerificationParams' => [
            'chainHash' => '8990e7a9aaed2ffed73dbd7092123d6f289930540d7651336225dc172e51b2ce',
            'publicKey' => '868f005eb8e6e4ca0a47c8a77ceaa5309a47978a7c71bc5cce96366b5d7a569937c529eeda66c7293784a9402801af31'
        ],
        
        // HTTP request timeout in seconds
        'timeout' => 10
    ],
    true,           // Enable caching (default)
    300,            // Chain info cache TTL (5 minutes)
    30              // Beacon cache TTL (30 seconds)
);
```

### Working with Rounds and Time

```php
// Get the round number for a specific time
$roundNumber = $client->roundAt(time() + 3600); // Round an hour from now

// Get the time when a round will be available
$roundTime = $client->roundTime(12345);
$whenAvailable = new DateTime("@$roundTime");

// Calculate current round
$currentRound = $client->roundAt();
```

### Error Handling

```php
try {
    $beacon = $client->getLatestBeacon();
} catch (\RuntimeException $e) {
    // Handle HTTP errors, verification failures, etc.
    error_log("Failed to get beacon: " . $e->getMessage());
} catch (\InvalidArgumentException $e) {
    // Handle invalid parameters
    error_log("Invalid parameters: " . $e->getMessage());
}
```

### Using the Caching Client Directly

```php
use Drand\Client\HttpClient;
use Drand\Client\CachingHttpClient;

// Create HTTP client with caching
$httpClient = new HttpClient('https://api.drand.sh');
$cachingClient = new CachingHttpClient(
    $httpClient,
    300,  // Chain info TTL (5 minutes)
    30    // Beacon TTL (30 seconds)
);

// Clear cache if needed
$cachingClient->clearCache();
```

### Using Multiple Chains

```php
use Drand\Client\HttpClient;
use Drand\Client\MultiChainClient;

// Create clients for different drand networks
$mainnet = new HttpClient('https://api.drand.sh');
$testnet = new HttpClient('https://pl-us.testnet.drand.sh');

// Combine them into a MultiChainClient
$multi = new MultiChainClient([$mainnet, $testnet]);

// Select by chain hash or beaconID
$mainnetClient = $multi->forChainHash('8990e7a9aaed2ffed73dbd7092123d6f289930540d7651336225dc172e51b2ce');
$testnetClient = $multi->forBeaconID('default');

// Use as a regular client (default is the first one)
$beacon = $multi->getLatestBeacon();
```

### Using the Fastest Node

```php
use Drand\Client\HttpClient;
use Drand\Client\FastestNodeClient;

// Create clients for several public endpoints
$clients = [
    new HttpClient('https://api.drand.sh'),
    new HttpClient('https://drand.cloudflare.com'),
    new HttpClient('https://drand.cloudflare.com/public')
];

// FastestNodeClient will always use the fastest node (auto-updated)
$fastest = new FastestNodeClient($clients);

$beacon = $fastest->getLatestBeacon();
printf("Fastest node randomness: %s\n", $beacon['randomness']);
```

### Selecting Network and Scheme via Configuration

You can use the built-in `Networks` class for easy selection of mainnet, testnet, or your own custom network:

```php
use Drand\Client\DrandClient;
use Drand\Client\Networks;

// Mainnet
$client = DrandClient::createDefault(
    Networks::MAINNET['url'],
    [
        'chainVerificationParams' => Networks::MAINNET['chainVerificationParams']
    ]
);

// Testnet
$client = DrandClient::createDefault(
    Networks::TESTNET['url'],
    [
        'chainVerificationParams' => Networks::TESTNET['chainVerificationParams']
    ]
);

// Custom network
$client = DrandClient::createDefault(
    'https://your.custom.drand.node',
    [
        'chainVerificationParams' => [
            'chainHash' => 'your_chain_hash',
            'publicKey' => 'your_public_key'
        ]
    ]
);
```

The `chainVerificationParams` option ensures you are connecting to the correct network and using the correct signature scheme. The scheme is automatically detected from the chain info.

### Advanced Usage Examples

#### Fetch beacon by time

```php
$round = $client->roundAt(time());
$beacon = $client->getBeacon($round);
printf("Randomness for round %d: %s\n", $round, $beacon->getRandomness());
```

#### Get time for a round

```php
$round = 1000000;
$timestamp = $client->roundTime($round);
echo "Round $round will be available at: " . date('c', $timestamp) . "\n";
```

#### Print chain info and signature scheme

```php
$chain = $client->getChain();
echo "Chain hash: " . $chain->getHash() . "\n";
echo "Signature scheme: " . $chain->getSchemeID() . "\n";
```

#### Use MultiChainClient and select by beaconID

```php
use Drand\Client\MultiChainClient;
use Drand\Client\HttpClient;
use Drand\Client\DrandClient;

$mainnet = new HttpClient(Drand\Client\Networks::MAINNET['url']);
$testnet = new HttpClient(Drand\Client\Networks::TESTNET['url']);
$multi = DrandClient::createMultiChain([$mainnet, $testnet]);

// Select mainnet by beaconID
$mainnetClient = $multi->getChain()->getHash() === Drand\Client\Networks::MAINNET['chainVerificationParams']['chainHash']
    ? $multi : null;
if ($mainnetClient) {
    $beacon = $mainnetClient->getBeacon(12345);
    echo "Mainnet randomness: " . $beacon->getRandomness() . "\n";
}
```

#### Use FastestNodeClient

```php
use Drand\Client\FastestNodeClient;

$clients = [
    new HttpClient('https://api.drand.sh'),
    new HttpClient('https://drand.cloudflare.com'),
];
$fastest = DrandClient::createFastestNode($clients);
$beacon = $fastest->getLatestBeacon();
echo "Fastest node randomness: " . $beacon->getRandomness() . "\n";
```

---

## Configuration Enums

This library uses modern PHP 8.2+ enums for configuration:

- **Network**: `Drand\Client\Network::MAINNET`, `TESTNET`, `DEVNET` — for selecting network and beaconID.
- **VerificationMode**: `Drand\Client\VerificationMode::ENABLED` or `DISABLED` — for enabling/disabling beacon signature verification.
- **CachePolicy**: `Drand\Client\CachePolicy::ENABLED` or `DISABLED` — for enabling/disabling HTTP response caching.

### Example: Creating a client with enums

```php
use Drand\Client\DrandClient;
use Drand\Client\Network;
use Drand\Client\VerificationMode;
use Drand\Client\CachePolicy;

$client = DrandClient::createDefault(
    Network::MAINNET->getDefaultUrl(),
    [
        'verificationMode' => VerificationMode::ENABLED,
        'cachePolicy' => CachePolicy::ENABLED,
        // 'chainVerificationParams' => ['chainHash' => '...', 'publicKey' => '...'],
    ]
);
```

### Example: Multi-chain client

```php
$mainnet = new HttpClient(Network::MAINNET->getDefaultUrl());
$testnet = new HttpClient(Network::TESTNET->getDefaultUrl());
$multi = DrandClient::createMultiChain([
    $mainnet,
    $testnet,
], [
    'verificationMode' => VerificationMode::ENABLED
]);
```

### Example: Fastest node client

```php
$client = DrandClient::createFastestNode([
    new HttpClient(Network::MAINNET->getDefaultUrl()),
    new HttpClient(Network::TESTNET->getDefaultUrl()),
], [
    'verificationMode' => VerificationMode::ENABLED
]);
```

---

## Configuration Files

- `infection.json.dist`: Template for Infection mutation testing configuration. Copy to `infection.json` to customize locally. Do **not** commit your local `infection.json`.

```bash
cp infection.json.dist infection.json
```

---

## Contributing

We welcome contributions and pull requests from the community!

For detailed contribution and testing guidelines, see [CONTRIBUTING.md](CONTRIBUTING.md).

**How to contribute:**
- **Pull Requests:** Pull requests are welcome! Please fork the repository and submit your changes via a PR. Describe your changes and reference any related issues.
- **Code style:** Follow PSR-12 and the project's existing conventions.
- **First-class callables:** Use first-class callable syntax (`$obj->method(...)`) for passing methods as callables in PHP 8.2+.
- **Tests:** Add or update tests for any new features or bugfixes. Ensure all tests pass.
- **Commits:** Use clear, descriptive commit messages in English.
- **Documentation:** Update the README and code comments as needed.
- **No unnecessary dependencies:** Keep the project minimal and focused.
- **Respect security best practices:** Never commit secrets or unsafe code.

For major changes, please open an issue first to discuss your proposal.

We appreciate your help in making this project better!

---

## License

This project is dual-licensed under the MIT and Apache 2.0 licenses:

- MIT License ([LICENSE-MIT](LICENSE-MIT) or https://opensource.org/licenses/MIT)
- Apache License, Version 2.0 ([LICENSE-APACHE](LICENSE-APACHE) or https://www.apache.org/licenses/LICENSE-2.0)

You may use this project under the terms of either license.