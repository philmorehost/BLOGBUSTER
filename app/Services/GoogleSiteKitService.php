<?php
class GoogleSiteKitService {

    private $accessToken;

    public function __construct($accessToken) {
        $this->accessToken = $accessToken;
    }

    public function getAnalyticsOverview($propertyId, $startDate = '7daysAgo', $endDate = 'today') {
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";
        
        $payload = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics'    => [['name' => 'activeUsers'], ['name' => 'screenPageViews'], ['name' => 'sessions']],
            'dimensions' => [['name' => 'date']]
        ];

        return $this->executeGoogleRequest($url, $payload);
    }

    public function getSearchConsoleKeywords($siteUrl) {
        $encodedSite = urlencode($siteUrl);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query";

        $payload = [
            'startDate'  => date('Y-m-d', strtotime('-30 days')),
            'endDate'    => date('Y-m-d'),
            'dimensions' => ['query'],
            'rowLimit'   => 10
        ];

        return $this->executeGoogleRequest($url, $payload);
    }

    public function fetchPageSpeedScore($targetUrl, $apiKey) {
        $url = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=" . urlencode($targetUrl) . "&key=" . $apiKey;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function executeGoogleRequest($url, $payload) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->accessToken,
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}