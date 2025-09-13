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
        $content = [
            'en' => $content,
        ];

        $fields = [
            'app_id' => '787d6428-2b70-463d-a858-eec955e1a922',
            'include_player_ids' => $id,
            'large_icon' => '@drawable/msilogo',
            'small_icon' => '@drawable/msilogo',
            'contents' => $content,
        ];

        $fields = json_encode($fields);
        // print("\nJSON sent:\n");
        error_log($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);
        curl_close($ch);

        // error_log($response);
    }
}
