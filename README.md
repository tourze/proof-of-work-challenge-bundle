# Proof of Work Challenge Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle providing Proof of Work (PoW) challenge system to defend against automated attacks, brute force attempts, and bot activities. This bundle implements the Hashcash algorithm with SHA-256 for web-optimized performance.

## Features

- **Hashcash Algorithm**: SHA-256 based proof-of-work with adjustable difficulty
- **Adaptive Difficulty**: Dynamic difficulty adjustment based on threat levels
- **Storage Abstraction**: Flexible storage backend (Cache/Redis) support
- **Security Integration**: Built-in challenge expiration and replay protection
- **Performance Optimized**: Sub-millisecond server-side validation

## Installation

```bash
composer require tourze/proof-of-work-challenge-bundle
```

## Configuration

Add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Tourze\ProofOfWorkChallengeBundle\ProofOfWorkChallengeBundle::class => ['all' => true],
];
```

## Usage

### 1. Issue Challenge

```php
use Tourze\ProofOfWorkChallengeBundle\Procedure\IssueChallengeHandler;

// Inject the handler in your service
public function __construct(
    private IssueChallengeHandler $issueChallengeHandler
) {}

// Issue a challenge for resource protection
$result = ($this->issueChallengeHandler)('login', $clientId);

// Response format:
[
    'success' => true,
    'challenge' => [
        'id' => 'challenge-id',
        'type' => 'hashcash',
        'challenge' => 'challenge-string',
        'difficulty' => 6,
        'expires_at' => 1234567890,
        'resource' => 'login'
    ]
]
```

### 2. Verify Challenge

```php
use Tourze\ProofOfWorkChallengeBundle\Procedure\VerifyChallengeHandler;

// Inject the handler in your service
public function __construct(
    private VerifyChallengeHandler $verifyChallengeHandler
) {}

// Verify the submitted proof
$result = ($this->verifyChallengeHandler)($challengeId, $proof);

// Success response:
[
    'success' => true,
    'resource' => 'login',
    'client_id' => 'client-id',
    'metadata' => []
]

// Failure response:
[
    'success' => false,
    'error' => 'Invalid proof',
    'code' => 'INVALID_PROOF'
]
```

## Algorithm Implementation

### Hashcash Algorithm

The bundle uses the modern Hashcash algorithm where the client must find a nonce such that:
```
SHA256(challenge + ':' + nonce)
```
produces a hash with the required number of leading zero bits based on difficulty level.

### Client-side JavaScript Implementation

```javascript
async function solveChallenge(challenge, difficulty) {
    let nonce = 0;
    while (true) {
        const attempt = challenge + ':' + nonce;
        const hash = await sha256(attempt);
        
        if (countLeadingZeroBits(hash) >= difficulty) {
            return nonce.toString();
        }
        nonce++;
    }
}

async function sha256(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

function countLeadingZeroBits(hexHash) {
    let zeroBits = 0;
    for (let i = 0; i < hexHash.length; i++) {
        const nibble = parseInt(hexHash[i], 16);
        if (nibble === 0) {
            zeroBits += 4;
        } else {
            zeroBits += Math.clz32(nibble) - 28;
            break;
        }
    }
    return zeroBits;
}
```

## Adaptive Difficulty

The bundle automatically adjusts difficulty based on:

- **Base Difficulty**: Default level of 4-6 bits
- **Resource Type**: Higher difficulty for sensitive resources (login, payment)
- **Client Behavior**: Dynamic adjustment based on recent attempt patterns
  - 5-10 attempts: 1.2x multiplier  
  - 10-20 attempts: 1.5x multiplier
  - 20-50 attempts: 2.0x multiplier
  - 50-100 attempts: 2.5x multiplier
  - 100+ attempts: 3.0x multiplier

## Security Features

- **Time-bound Challenges**: 5-minute expiration by default
- **Anti-replay Protection**: Each challenge can only be used once
- **Replay Detection**: Challenge marking and validation
- **Threat Escalation**: Progressive difficulty increase
- **Storage Abstraction**: Secure challenge persistence

## Performance Characteristics

- **4-bit difficulty**: Average < 0.1 seconds
- **8-bit difficulty**: Average ~1 second
- **12-bit difficulty**: Average ~10 seconds
- **16-bit difficulty**: Average ~1 minute

The implementation uses adaptive difficulty to balance security and user experience.

## License

MIT License