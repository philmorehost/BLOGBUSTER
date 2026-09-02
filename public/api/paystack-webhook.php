<?php
// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Retrieve the incoming raw payload and HTTP headers
$input = file_get_contents('php_input');
$paystackSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Load database connection and Paystack handler service
require_once __DIR__ . '/../app/Config/database.php'; // Defines $pdo connection
require_once __DIR__ . '/../app/Services/PaystackLicenseHandler.php';

// Define your Paystack Secret Key
$paystackSecret = 'sk_live_YOUR_PAYSTACK_SECRET_KEY';

// Step 1: Validate HMAC Signature to ensure request originates from Paystack
if (!$paystackSignature || $paystackSignature !== hash_hmac('sha512', $input, $paystackSecret)) {
    http_response_code(400);
    exit('Invalid Paystack Signature');
}

// Step 2: Parse Event Data
$event = json_decode($input, true);

if (isset($event['event']) && $event['event'] === 'charge.success') {
    $reference = $event['data']['reference'] ?? null;

    if ($reference) {
        // Step 3: Verify and Grant License/Access via PaystackLicenseHandler
        $result = PaystackLicenseHandler::verifyAndGrantLicense($reference, $paystackSecret, $pdo);

        if ($result['status']) {
            // Optional: Log success or trigger order fulfillment emails here
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'License created',
                'license_key' => $result['license_key']
            ]);
            exit();
        }
    }
}

// Respond with 200 OK to inform Paystack the webhook was received
http_response_code(200);
echo json_encode(['status' => 'ignored']);
?>