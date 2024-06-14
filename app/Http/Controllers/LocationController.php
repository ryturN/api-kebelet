<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/locations",
     *     operationId="locationList",
     *     tags={"Location"},
     *     summary="Location List",
     *     description="Location List here",
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
     *         description="Locations retrieved successfully",
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
        $Locations = Location::paginate($size);
        return $this->sendResponse($Locations, 'Locations retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/locations",
     *     operationId="locationCreate",
     *     tags={"Location"},
     *     summary="Location Create",
     *     description="Location Create here",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Location successfully created",
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
            'name' => ['required', 'string', 'min:3', 'max:255']
        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $location = Location::create($input);
        return $this->sendResponse($location, 'Location successfully created', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/locations/{id}",
     *     operationId="locationRetrieve",
     *     tags={"Location"},
     *     summary="Location Retrieve",
     *     description="Location Retrieve here",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Location id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Location not found"),
     * )
     */
    public function show($id)
    {
        $Location = Location::find($id);
        if (is_null($Location)) {
            return $this->sendError('Location not found.');
        }
        return $this->sendResponse($Location, 'Location retrieved successfully.');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/locations/{id}",
     *     operationId="LocationUpdate",
     *     tags={"Location"},
     *     summary="Location Update",
     *     description="Location Update here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Location id",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Location name",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location updated successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Validation Error."),
     *     @OA\Response(response=409, description="Failed to update the Location."),
     *     @OA\Response(response=404, description="Location not found."),
     * )
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => ['string', 'min:3', 'max:255']
        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }
        $record = Location::find($id);
        if(is_null($record)) {
            return $this->sendError('Location not found.');
        }
        $success = $record->update($input);
        if(!$success) {
            return $this->sendError('Failed to update the Location.', 400);
        }
        return $this->sendResponse($record, 'Location updated successfully.');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/locations/{id}",
     *     operationId="locationDelete",
     *     tags={"Location"},
     *     summary="Location Delete",
     *     description="PrLocationoduct Delete here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Location id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location deleted successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Failed to delete the Location."),
     *     @OA\Response(response=404, description="Location not found."),
     * )
     */
    public function destroy($id)
    {
        $record = Location::find($id);
        if(is_null($record)) {
            return $this->sendError('Location not found.');
        }
        $success = $record->delete();
        if(!$success) {
            return $this->sendError('Failed to delete the Location.', 400);
        }
        return $this->sendResponse([], 'Location deleted successfully.');
    }
}
