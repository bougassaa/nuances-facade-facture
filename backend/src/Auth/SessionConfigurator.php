<?php

declare(strict_types=1);

namespace Nuances\Facture\Auth;

/**
 * Configure la session PHP persistante (cookie + fichiers hors purge OVH).
 */
final class SessionConfigurator
{
    /** 365 jours. */
    public const LIFETIME_SECONDS = 31_536_000;

    public static function sessionsPath(string $root): string
    {
        return $root . '/storage/sessions';
    }

    public static function ensureSessionsDirectory(string $root): string
    {
        $path = self::sessionsPath($root);
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Impossible de créer le dossier de sessions : ' . $path);
        }
        return $path;
    }

    /**
     * Paramètre et démarre la session si elle n’est pas déjà active.
     */
    public static function start(string $root, string $sessionName, bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $savePath = self::ensureSessionsDirectory($root);
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME_SECONDS);
        session_save_path($savePath);
        session_name($sessionName);
        session_set_cookie_params([
            'lifetime' => self::LIFETIME_SECONDS,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
