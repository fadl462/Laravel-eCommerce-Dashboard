<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint — Stripe calls this directly, so it's excluded from Sanctum
 * auth and CSRF in bootstrap/app.php (see the "Wiring notes" section of the
 * README). Trust comes entirely from the signature check below, not from
 * who's allowed to call the route.
 */
class StripeWebhookController extends Controller
{
    public function __construct(protected StripeGatewayService $stripe)
    {
    }

    public function handle(Request $request)
    {
        $signature = $request->header('Stripe-Signature');

        try {
            $event = StripeGatewayService::verifySignature($request->getContent(), $signature);
        } catch (\Throwable $e) {
            Log::warning('Rejected Stripe webhook: invalid signature.');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $this->stripe->handleWebhook($event);

        return response()->json(['received' => true]);
    }
}
