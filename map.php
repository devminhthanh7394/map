<?php

namespace Botble\ClinicDental\Http\Controllers\Api;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\ClinicDental\Enums\ClinicStatusEnums;
use Botble\ClinicDental\Enums\MarkerMapStatusEnums;
use Botble\ClinicDental\Http\Resources\Admin\ListServicesResource;
use Botble\ClinicDental\Http\Resources\Admin\ResearchClinicResource;
use Botble\ClinicDental\Http\Resources\Admin\ResearchServiceResource;
use Botble\ClinicDental\Http\Resources\Admin\ServiceResource;
use Botble\ClinicDental\Http\Resources\ListMapResource;
use Botble\ClinicDental\Repositories\Interfaces\ClinicInterface;
use Botble\ClinicDental\Repositories\Interfaces\ClinicMarkerMapInterface;
use Botble\Services\Repositories\Interfaces\ServicesInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PublicController extends Controller
{
    /**
     * @var ServicesInterface
     * @var ClinicInterface
     */
    protected $servicesRepository;
    protected $clinicRepository;


    /**
     * @param ServicesInterface $servicesRepository
     * @param ClinicInterface $clinicRepository
     * @param clinicMarkerMapRepositoryInterface $clinicMarkerMapRepository
     */
    public function __construct(ServicesInterface $servicesRepository, ClinicInterface $clinicRepository, ClinicMarkerMapInterface $clinicMarkerMapRepository)
    {
        $this->servicesRepository = $servicesRepository;
        $this->clinicRepository = $clinicRepository;
        $this->clinicMarkerMapRepository = $clinicMarkerMapRepository;
    }

    /**
     * List services
     *
     * @group Services
     *
     * @param Request $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function research_data(Request $request, BaseHttpResponse $response)
    {
        $clinics = $this->clinicRepository->getModel()
            ->with('map')
            ->where('name', 'like', "%" . $request->keyword . "%")
            ->where('status', ClinicStatusEnums::PUBLISHED)
            ->whereHas('map')
            ->orderBy('name')
            ->get();

        $services = $this->servicesRepository->getModel()
            ->where('name', 'like', "%" . $request->keyword . "%")
            ->where('status', BaseStatusEnum::PUBLISHED)
            ->orderBy('name')->get();

        return $response
            ->setData([
                'clinic' => ResearchClinicResource::collection($clinics),
                'service' => ResearchServiceResource::collection($services),
            ])
            ->setMessage('Success')
            ->toApiResponse();
    }


    /**
     * List services
     *
     * @group Services
     *
     * @param Request $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */

    public function get_clinic_location(Request $request, BaseHttpResponse $response)
    {
        $radius = $request->input('radius', 10);
        if ($radius > 200) {
            $radius = 200;
        }

        $list = $this->clinicMarkerMapRepository->getModel()->whereHas('clinic', function ($query) {
            $query->where('status', MarkerMapStatusEnums::PUBLISHED);
        })->with('clinic')->get();

        $result = $this->calc_location($request->lat, $request->lng, $list);

        $data = $result->where('calc', '<', $radius)->sortBy('calc');


        return $response
            ->setData(ListMapResource::collection($data))
            ->setMessage('Success')
            ->toApiResponse();
    }

    public function calc_location($lat, $lng, $list)
    {
        foreach ($list as $key => $item) {
            $list[$key]['calc'] = $this->haversineGreatCircleDistance($lat, $lng, $item->latitude, $item->longitude);
        }

        return $list;
    }

    function haversineGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
}
