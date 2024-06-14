<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Toilet;
use App\Models\ToiletVisit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class CheckinController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/checkins",
     *     operationId="checkinList",
     *     tags={"Checkin"},
     *     summary="Checkin List",
     *     description="Checkin List here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Paginate size",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Paginate page",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkins retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function index(Request $request)
    {
        if ($request->input('size')) {
            $size = $request->input('size');
        } else {
            // Set default pagination size
            $size = 10;
        }
        $Checkins = Checkin::paginate($size);
        return $this->sendResponse($Checkins, 'Checkins retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/checkins",
     *     operationId="checkinCreate",
     *     tags={"Checkin"},
     *     summary="Checkin Create",
     *     description="Checkin Create here",
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"toilet_id"},
     *                 @OA\Property(property="toilet_id", type="number")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Checkin successfully created",
     *          @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *          response=422,
     *          description="Unprocessable Entity",
     *          @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Bad request")
     * )
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'toilet_id' => ['required', 'integer']
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $toilet = Toilet::find($request->input('toilet_id'));
        if (is_null($toilet)) {
            return $this->sendError('Toilet not found.');
        }

        // Get user by token
        $userId = NULL;
        $token = $request->bearerToken();
        $token = PersonalAccessToken::findToken($token);
        if($token) {
            $user = $token->tokenable;
            $userId = $user ? $user->id : null;
        }

        $checkin = Checkin::create([
            'user_id' => $userId,
            'toilet_id' => $toilet->id,
            'point' => 1
        ]);

        $ipAddress = $request->getClientIp();
        // $oneHourAgo = now()->subHour();
        $lastVisit = ToiletVisit::where('toilet_id', $request->toilet_id)
            ->where(function ($query) use ($userId, $ipAddress) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('ip_address', $ipAddress);
                }
            })
            ->orderBy('last_visited_at', 'desc')
            ->first();

        if ($lastVisit && Carbon::parse($lastVisit->last_visited_at)->isToday()) {
            return $this->sendResponse('already visit today!',200);
        }

        $toiletVisitData = [
            'toilet_id' => $request->toilet_id,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'last_visited_at' => now()
        ];

        if ($lastVisit) {
            $lastVisit->update($toiletVisitData);
        } else {
            ToiletVisit::create($toiletVisitData);
        }

        $response = [
            'checkin' => $checkin
        ];
        return $this->sendResponse($response, 'Toilet retrieved successfully.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/checkins/{id}",
     *     operationId="checkinRetrieve",
     *     tags={"Checkin"},
     *     summary="Checkin Retrieve",
     *     description="Checkin Retrieve here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checkin id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkin retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Checkin not found"),
     * )
     */
    public function show($id)
    {
        $Checkin = Checkin::find($id);
        if (is_null($Checkin)) {
            return $this->sendError('Checkin not found.');
        }
        return $this->sendResponse($Checkin, 'Checkin retrieved successfully.');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/checkins/{id}",
     *     operationId="checkinDelete",
     *     tags={"Checkin"},
     *     summary="Checkin Delete",
     *     description="PrCheckinoduct Delete here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checkin id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkin deleted successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Failed to delete the Checkin."),
     *     @OA\Response(response=404, description="Checkin not found."),
     * )
     */
    public function destroy($id)
    {
        $record = Checkin::find($id);
        if (is_null($record)) {
            return $this->sendError('Checkin not found.');
        }
        $success = $record->delete();
        if (!$success) {
            return $this->sendError('Failed to delete the Checkin.', 400);
        }
        return $this->sendResponse([], 'Checkin deleted successfully.');
    }
}
