<?php

namespace App\Helpers;

class SendNotif
{
    public static function sendMessage($content, array $id)
    {
        // Skip external notification calls during tests to keep runs fast and deterministic
        if (app()->environment('testing')) {
            return;
        }

        // Get OneSignal APP_ID from config (fallback to env for backward compatibility)
        $appId = config('services.onesignal.app_id') ?: env('ONESIGNAL_APP_ID');

        // If still not configured, try to get from hardcoded value for legacy support
        if (! $appId) {
            $appId = '787d6428-2b70-463d-a858-eec955e1a922';
        }

        $content = [
            'en' => $content,
        ];

        $fields = [
            'app_id' => $appId,
            'include_player_ids' => $id,
            'large_icon' => '@drawable/msilogo',
            'small_icon' => '@drawable/msilogo',
            'contents' => $content,
        ];

        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);

        // Simple error logging without breaking execution
        if ($response === false && app()->environment('local', 'development')) {
            error_log('OneSignal error: '.curl_error($ch));
        }

        curl_close($ch);
    }
}
