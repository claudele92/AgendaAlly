<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Helpers\ResponseError;
use App\Http\Requests\CountryInvitation\StatusRequest;
use App\Http\Requests\CountryInvitation\StoreRequest;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\CountryInvitationResource;
use App\Repositories\CountryInviteRepository\CountryInviteRepository;
use App\Services\CountryInviteService\CountryInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CountryInviteController extends CountryBaseController
{
    public function __construct(
        private CountryInviteRepository $repository,
        private CountryInviteService $service
    )
    {
        parent::__construct();
    }

    public function paginate(FilterParamsRequest $request): AnonymousResourceCollection|JsonResponse
    {
        if (!$this->countryId) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        $invites = $this->repository->paginate($request->merge(['country_id' => $this->countryId])->all());

        return CountryInvitationResource::collection($invites);
    }

    public function create(StoreRequest $request): JsonResponse
    {
        if (!$this->countryId) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        $result = $this->service->create($this->countryId, $request->validated());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            CountryInvitationResource::make(data_get($result, 'data'))
        );
    }

    public function changeStatus(int $id, StatusRequest $request): JsonResponse
    {
        if (!$this->countryId) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        $result = $this->service->changeStatus($this->countryId, $id, $request->validated());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            CountryInvitationResource::make(data_get($result, 'data'))
        );
    }

    public function delete(FilterParamsRequest $request): JsonResponse
    {
        if (!$this->countryId) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        $this->service->deleteInvitations($request->input('ids', []), $this->countryId);

        return $this->successResponse(__('errors.' . ResponseError::NO_ERROR, locale: $this->language));
    }
}
