<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * These are payment-gateway endpoints hit either by an authenticated
     * fetch() call from the SPA (paypal-process, to open a PayPal order -
     * no Blade-rendered CSRF meta tag exists for it to read) or
     * server-to-server by the gateway itself (the webhook routes) - a
     * request with no browser session at all can never carry a valid
     * CSRF token, so if this middleware is ever active on the group these
     * routes sit under, they would fail every single time with no way to
     * reconcile. Listed here (rather than relying on whatever middleware
     * group happens to apply in a given environment) so the exemption
     * holds regardless of that.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/v1/dashboard/user/paypal-process',
        'api/v1/webhook/paypal/payment',
        'api/v1/webhook/stripe/payment',
    ];
}
