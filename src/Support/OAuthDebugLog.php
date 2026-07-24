<?php
declare(strict_types=1);

namespace Crustum\Tessera\Support;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Optional OAuth flow debug logging to the dedicated `tessera` log scope.
 *
 * Enable with `Tessera.debugOAuth` (host config / env). Never logs secrets,
 * authorization codes, or access tokens — only safe request metadata.
 */
class OAuthDebugLog
{
    /**
     * Log scope name for FileLog `scopes` configuration.
     */
    public const SCOPE = 'tessera';

    /**
     * Whether Tessera OAuth debug logging is enabled.
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return (bool)Configure::read('Tessera.debugOAuth', false);
    }

    /**
     * Write a debug line when enabled.
     *
     * @param string $message Human-readable event
     * @param array<string, mixed> $context Safe structured context
     * @return void
     */
    public static function write(string $message, array $context = []): void
    {
        if (!static::enabled()) {
            return;
        }

        $suffix = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);

        Log::debug('[tessera-oauth] ' . $message . $suffix, ['scope' => [self::SCOPE]]);
    }
}
