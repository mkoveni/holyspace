<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\RecoveryCodeDelivery;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Throwable;

#[AsAlias(RecoveryCodeDelivery::class)]
final class PrivateRecoveryCodeFile implements RecoveryCodeDelivery
{
    public function write(string $code, string $destination): void
    {
        $handle = @fopen($destination, 'x');
        if (!$handle) {
            throw new RuntimeException('Cannot create a new recovery code file.');
        }
        try {
            if (!chmod($destination, 0600) || fwrite($handle, $code."\n") !== strlen($code) + 1) {
                throw new RuntimeException('Cannot secure the recovery code file.');
            }
        } catch (Throwable $error) {
            unlink($destination);
            throw $error;
        } finally {
            fclose($handle);
        }
    }
}
