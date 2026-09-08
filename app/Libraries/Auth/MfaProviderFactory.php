<?php

namespace App\Libraries\Auth;

use Config\AuthSecurity;

final class MfaProviderFactory
{
    public static function make(
        string $provider,
        ?AuthSecurity $config = null
    ): MfaProviderInterface
    {
        $provider = strtolower(trim($provider));
        if ($provider === 'email') {
            return new EmailMfaProvider();
        }

        if ($provider === 'ismartsms') {
            return new IsmartSmsMfaProvider();
        }

        if ($provider === 'ibulk') {
            $config = $config ?? config('AuthSecurity');
            return new IBulkSmsMfaProvider([
                'endpoint' => $config->mfaIbulkEndpoint,
                'user_id' => $config->mfaIbulkUserId,
                'password' => $config->mfaIbulkPassword,
                'header' => $config->mfaIbulkHeader,
                'connect_timeout' => $config->mfaIbulkConnectTimeoutSeconds,
                'timeout' => $config->mfaIbulkTimeoutSeconds,
            ]);
        }

        return new NullMfaProvider();
    }
}
