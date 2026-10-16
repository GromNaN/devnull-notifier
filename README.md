dev-null.sh Notifier
====================

Provides [dev-null.sh](https://dev-null.sh) integration for Symfony Notifier.

dev-null.sh is a social network for developers where you publish commits from your
terminal. There is no login: a commit is signed locally with an SSH key that is on your
GitHub account, and dev-null.sh checks the signature against the public keys GitHub
publishes for you. This bridge does the same from a Symfony application.

DSN example
-----------

```
DEVNULL_DSN=devnull://default?private_key=%2Fhome%2Fme%2F.ssh%2Fid_ed25519
```

DSN options
-----------

| Option        | Required | Description                                                                                 |
|---------------|----------|---------------------------------------------------------------------------------------------|
| `private_key` | yes      | Path to the OpenSSH private key used to sign the commit.                                    |
| `passphrase`  | no       | Passphrase of the private key, if it is encrypted.                                          |
| `namespace`   | no       | Signature namespace. Defaults to `devnull-commit`, the value dev-null.sh expects.           |
| `ssl`         | no       | Set to `0` to use `http` instead of `https`, for a self-hosted instance. Defaults to `1`.    |

The host is `default` for dev-null.sh, or the domain of a self-hosted instance.

Usage
-----

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        chatter_transports:
            devnull: '%env(DEVNULL_DSN)%'
```

```php
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\ChatterInterface;

public function notify(ChatterInterface $chatter): void
{
    $chatter->send(new ChatMessage('Shipped the dev-null.sh notifier bridge!'));
}
```

To reply to an existing commit, pass its hash or link:

```php
use GromNaN\Notifier\Bridge\DevNull\DevNullOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new DevNullOptions())->replyTo('0be0130');

$chatter->send(new ChatMessage('Agreed!', $options));
```

How it works
------------

A commit body must be between 3 and 280 characters. The transport builds the payload
`<timestamp>\n<nonce>\n<reply to>\n<body>`, signs it with the private key in the SSHSIG
format (`ssh-keygen -Y sign` equivalent, implemented with
[phpseclib](https://phpseclib.com/)), and posts it to `/api/cli/commits` with the signature
in the `X-SSH-Signature` header. The private key never leaves the machine, and only the
signature is sent.

The signing is byte-for-byte identical to `ssh-keygen -Y sign`, so a commit made through
this bridge is indistinguishable from one made with the `devnull commit` script.

Requirements
------------

The private key must be one of the keys on the GitHub account of the dev-null.sh user you
commit as. Ed25519 and RSA keys are supported.

Installation
------------

```bash
composer require gromnan/devnull-notifier
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    GromNaN\Notifier\Bridge\DevNull\DevNullNotifierBundle::class => ['all' => true],
];
```

The bundle registers the transport factory with the Notifier. Without the bundle, register
the factory manually as a service tagged `chatter.transport_factory`.

Sponsor
-------

This package is not affiliated with dev-null.sh. The dev-null.sh service is built and
maintained by [Nuno Maduro](https://github.com/nunomaduro).
