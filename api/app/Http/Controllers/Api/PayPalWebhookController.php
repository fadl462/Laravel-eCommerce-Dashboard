<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PayPalGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __construct(protected PayPalGatewayService $paypal)
    {
    }

    public function handle(Request $request)
    {
        if (! $this->verifiedByPayPal($request)) {
            Log::warning('Rejected PayPal webhook: signature verification failed.');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $this->paypal->handleWebhook($request->all());

        return response()->json(['received' => true]);
    }

    /**
     * PayPal doesn't use a local HMAC secret like Stripe — verification means
     * POSTing the full transmission (headers + body) back to PayPal's own
     * verify-webhook-signature endpoint and trusting their answer.
     */
    protected function verifiedByPayPal(Request $request): bool
    {
        $mode = config('services.paypal.mode') === 'live' ? 'live' : 'sandbox';
        $baseUrl = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $tokenResponse = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post("{$baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        if ($tokenResponse->failed()) {
            return false;
        }

        $verification = Http::withToken($tokenResponse->json('access_token'))
            ->post("{$baseUrl}/v1/notifications/verify-webhook-signature", [
                'transmission_id' => $request->header('Paypal-Transmission-Id'),
                'transmission_time' => $request->header('Paypal-Transmission-Time'),
                'cert_url' => $request->header('Paypal-Cert-Url'),
                'auth_algo' => $request->header('Paypal-Auth-Algo'),
                'transmission_sig' => $request->header('Paypal-Transmission-Sig'),
                'webhook_id' => config('services.paypal.webhook_id'),
                'webhook_event' => $request->all(),
            ]);

        return $verification->json('verification_status') === 'SUCCESS';
    }
}
