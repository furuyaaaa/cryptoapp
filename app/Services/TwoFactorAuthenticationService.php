<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA (TOTP) の発行・検証・復旧コード管理を担うサービス。
 *
 * ルール:
 * - secret / recovery_codes は User の cast (encrypted / encrypted:array) により
 *   DB 保存時点で暗号化されていることを前提とする。本サービスでは平文で扱う。
 * - 検証の際に recovery code を使った場合はその 1 個を消費してリスト化から外す。
 * - window は両サイド 1 ステップ (=計 ±30 秒) を許容。
 */
class TwoFactorAuthenticationService
{
    private const RECOVERY_CODE_COUNT = 8;
    private const RECOVERY_CODE_LENGTH = 10;
    private const WINDOW = 1;

    public function __construct(private readonly Google2FA $google2fa)
    {
    }

    /**
     * 新しい Base32 TOTP シークレットを生成する。
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * 指定された secret に対し 6 桁のコードを検証する。
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || ! ctype_digit($code)) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code, self::WINDOW);
    }

    /**
     * 復旧コードを生成する。
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::lower(Str::random(self::RECOVERY_CODE_LENGTH));
        }

        return $codes;
    }

    /**
     * 入力が有効な復旧コードに一致するか検証し、消費する。
     * マッチすれば残りのコード配列を返し、そうでなければ null。
     *
     * @param array<int, string> $codes
     * @return array<int, string>|null
     */
    public function consumeRecoveryCode(array $codes, string $input): ?array
    {
        $normalized = Str::lower(trim($input));
        if ($normalized === '') {
            return null;
        }

        foreach ($codes as $index => $stored) {
            if (hash_equals((string) $stored, $normalized)) {
                unset($codes[$index]);

                return array_values($codes);
            }
        }

        return null;
    }

    /**
     * 認証アプリ用の otpauth:// URL を作成する。
     */
    public function otpauthUrl(User $user, string $secret): string
    {
        $issuer = config('app.name', 'Laravel');

        return $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);
    }

    /**
     * otpauth:// を埋め込んだ SVG QR コードを返す。
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192, 1),
            new SvgImageBackEnd(),
        );

        $svg = (new Writer($renderer))->writeString($this->otpauthUrl($user, $secret));

        // `<?xml` ヘッダがそのまま埋め込まれると Inertia 経由の render で壊れることがあるため
        // 先頭の XML 宣言は除去してルートの <svg> タグから返す。
        return trim(preg_replace('/^<\?xml.*?\?>/', '', $svg) ?? $svg);
    }
}
