<?php
class SocialDispatcher {

    public static function postToFacebook($pageId, $accessToken, $message, $link = null) {
        $url = "https://graph.facebook.com/v18.0/{$pageId}/feed";
        $payload = [
            'message' => $message,
            'access_token' => $accessToken
        ];
        if ($link) {
            $payload['link'] = $link;
        }

        return self::sendCurlRequest($url, $payload);
    }

    public static function postToX($bearerToken, $text) {
        $url = "https://api.twitter.com/2/tweets";
        $headers = [
            "Authorization: Bearer " . $bearerToken,
            "Content-Type: application/json"
        ];
        $payload = json_encode(['text' => $text]);

        return self::sendCurlRequest($url, $payload, $headers, true);
    }

    private static function sendCurlRequest($url, $data, $headers = [], $isJson = false) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        if ($isJson) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $httpCode, 'response' => json_decode($response, true)];
    }
}