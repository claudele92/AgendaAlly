<?php

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\CartDetail;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopLocation;
use App\Repositories\PaymentRepository\PaymentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends RestBaseController
{
    private PaymentRepository $repository;

    /**
     * @param PaymentRepository $repository
     */
    public function __construct(PaymentRepository $repository)
    {
        parent::__construct();

        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * When `shop_id` is given, the list is scoped to that shop's country
     * (plus cash/wallet, which are always available) rather than every
     * globally active gateway. `location_type` (ShopLocation::PRODUCT or
     * ShopLocation::SERVICE) is then required, since a shop's product and
     * service arms can sit in different countries. Without `shop_id`, the
     * old unscoped global list is returned unchanged — except that
     * `cart_id`, when given, excludes Orange Money/MTN Mobile Money if
     * that cart's items span more than one shop: those two gateways
     * settle directly into one shop's own merchant account, so there is
     * no single account a split-shop cart could pay into. The customer
     * should never be offered an option that would then fail at payment
     * time — see MtnService/OrangeService::resolveGatewayShopId() for
     * the backend backstop if this is ever reached anyway.
     *
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $shopId = $request->input('shop_id');

        if (!$shopId) {
            $payments = $this->repository->paymentsList($request->merge(['active' => 1])->all());

            $cartId = $request->input('cart_id');

            if ($cartId && $this->cartSpansMultipleShops((int) $cartId)) {
                $payments = $payments->reject(
                    fn (Payment $payment) => in_array($payment->tag, [Payment::TAG_ORANGE, Payment::TAG_MTN], true)
                );
            }

            return PaymentResource::collection($payments);
        }

        $locationType = (int) $request->input('location_type');

        if (!in_array($locationType, [ShopLocation::PRODUCT, ShopLocation::SERVICE])) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_400,
                'message' => __('errors.' . ResponseError::ERROR_400, locale: $this->language),
            ]);
        }

        /** @var Shop|null $shop */
        $shop    = Shop::find($shopId);
        $country = $shop?->checkoutCountry($locationType);

        if (!$country) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_400,
                'message' => __('errors.' . ResponseError::ERROR_400, locale: $this->language),
            ]);
        }

        $payments = Payment::query()
            ->whereIn('id', $country->activePaymentIds())
            ->get();

        return PaymentResource::collection($payments);
    }


    private function cartSpansMultipleShops(int $cartId): bool
    {
        return CartDetail::whereHas('userCart', fn ($q) => $q->where('cart_id', $cartId))
            ->distinct()
            ->count('shop_id') > 1;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        /** @var Payment $payment */
        $payment = $this->repository->paymentDetails($id);

        if (!$payment || !$payment->active) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        return $this->successResponse(__('errors.' . ResponseError::NO_ERROR, locale: $this->language), PaymentResource::make($payment));
    }

}
