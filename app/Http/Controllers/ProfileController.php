<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    //
    /**
     * @OA\Get(
     *     path="/api/v1/profile",
     *     operationId="show-profile",
     *     tags={"Profile"},
     *     summary="Profile",
     *     description="Profile User",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Toilet retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     * )
     */
    public function index()
    {
        $user = auth()->user();
        if (is_null($user)) {
            return $this->sendError([], 'you must login first');
        }
        return $this->sendResponse($user->toArray(), 'Profile retrieved successfully');
    }
    /**
     * @OA\Post(
     *     path="/api/v1/profile/{id}",
     *     operationId="edit profile",
     *     tags={"Profile"},
     *     summary="Edit Profile",
     *     description="Edit Profile here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="username", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="password", type="string"),
     *                 @OA\Property(property="old_password", type="string"),
     *                 @OA\Property(property="profile_picture", type="file", format="binary"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Review successfully created",
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
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (is_null($user)) {
            return $this->sendError('you must login first', 403);
        }
        if ($user->id != $id) {
            return $this->sendError('you are not allowed to update this profile', 400);
        }

        $requestData = $request->all();

        $filteredData = array_filter($requestData, function ($value) {
            return !is_null($value) && $value !== '';
        });

        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $fileName = 'profiles/' . time() . '-' . $user->username;
            try {
                Storage::disk('linode')->put($fileName, file_get_contents($file), 'public');
                Log::info('File successfully uploaded to bucket: ' . $fileName);
                $filteredData['profile_picture'] = "https://ap-south-1.linodeobjects.com/kebelet-media" . '/' . $fileName;
            } catch (\Exception $e) {
                Log::error('Error Upload File' . $e->getMessage());
                return $this->sendError($e->getMessage());
            }
        }

        if (!empty($request->get('password'))) {
            if (!$user->google_id && !$user->facebook_id) {
                if (!Hash::check($request->get('old_password'), $user->password)) {
                    return $this->sendError('old password is wrong', 400);
                }
            }
            $filteredData['password'] = Hash::make($request->get('password'));
        }

        $user->update($filteredData);

        return $this->sendResponse($user, 'Profile updated successfully', 200);
    }
}
