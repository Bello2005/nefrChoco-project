<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Segundo factor por TOTP.
 *
 * [CONFIRMAR] qué artículo de la Res. 1644 de 2026 lo respalda.
 *
 * Se eligió TOTP y no un código por SMS o correo justamente por el contexto:
 * una aplicación de autenticación genera el código sin red, mientras que un
 * SMS depende de la misma cobertura que falla en los municipios del Chocó.
 */
class TwoFactorAuthenticationService
{
    private const RECOVERY_CODE_COUNT = 8;

    /**
     * Tolerancia de un período (±30 s) alrededor del actual.
     *
     * Muchos teléfonos en zona rural no sincronizan la hora por red, y un
     * reloj corrido medio minuto dejaría al profesional fuera de la plataforma
     * justo cuando necesita atender.
     */
    private const WINDOW = 1;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, $code, self::WINDOW);
    }

    /**
     * QR en SVG incrustado en la propia página.
     *
     * Se genera en el servidor y viaja como marcado, no como imagen ni desde
     * un servicio externo: no agrega una petición más en una red lenta y el
     * secreto nunca sale hacia un tercero.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 0), new SvgImageBackEnd));

        // Se quita la declaración XML para poder incrustarlo dentro del HTML.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($this->otpauthUri($user, $secret)));
    }

    /** @return array<int, string> */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** Un código de recuperación sirve una sola vez y se descarta al usarlo. */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(Str::upper(trim($code)), $codes, strict: true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    private function otpauthUri(User $user, string $secret): string
    {
        $issuer = config('app.name');

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($user->email),
            $secret,
            rawurlencode($issuer),
        );
    }
}
