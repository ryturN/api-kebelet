<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewImages;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/reviews",
     *     operationId="reviewList",
     *     tags={"Review"},
     *     summary="Review List",
     *     description="Review List here",
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
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="filter by user_id",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $size = $request->input('size', 10);

        $query = Review::query();

        if ($user->role === 'admin' || $user->role === 'superadmin') {
            if ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }
        } else {
            $query->where('user_id', $user->id);
        }
        $reviews = $query->paginate($size);
        return $this->sendResponse($reviews, 'Reviews retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/reviews",
     *     operationId="reviewCreate",
     *     tags={"Review"},
     *     summary="Review Create",
     *     description="Review Create here",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"toilet_id", "rating","description"},
     *                 @OA\Property(property="toilet_id", type="integer"),
     *                 @OA\Property(property="rating", type="integer"),
     *                 @OA\Property(property="total_toilet", type="string"),
     *                 @OA\Property(property="cleanness", type="string"),
     *                 @OA\Property(property="facility", type="string"),
     *                 @OA\Property(property="crowded", type="string"),
     *                 @OA\Property(property="img", type="file", format="binary"),
     *                 @OA\Property(property="description", type="string"),
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
    public function store(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'toilet_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:0', 'max:100'],
            'total_toilet' => ['required', 'string'],
            'cleanness' => ['required', 'string'],
            'facility' => ['required', 'string'],
            'crowded' => ['required', 'string'],
            'img' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description' => ['string', 'min:3', 'max:255'],
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $toilet = Toilet::find($request->input('toilet_id'));
        if (is_null($toilet)) {
            return $this->sendError('Toilet not found.');
        }
        $user = Auth::user();
        $description = '';
        if ($request->input('description')) {
            $description = $request->input('description');
        }
        $review = Review::create([
            'user_id' => $user->id,
            'toilet_id' => $toilet->id,
            'rating' => $request->input('rating'),
            'description' => $description,
            'total_toilet' => $request->input('total_toilet'),
            'cleanness' => $request->input('cleanness'),
            'facility' => $request->input('facility'),
            'crowded' => $request->input('crowded'),
        ]);
        return $this->sendResponse($review, 'Review successfully created', 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/reviews/upload",
     *     operationId="Upload Review Image",
     *     tags={"Review"},
     *     summary="Review Images",
     *     description="Upload multiple review images",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"reviews_id","img[]"},
     *                 @OA\Property(property="reviews_id", type="integer"),
     *                 @OA\Property(
     *                     property="img[]",
     *                     type="array",
     *                     @OA\Items(
     *                         type="file",
     *                         format="binary"
     *                     )
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Files Successfully uploaded",
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

    public function storeImage(Request $request)
    {
        $files = $request->file('img');
        $reviewId = $request->get('reviews_id');

        $uploadedFiles = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $fileName = 'reviews/' . time() . '-' . str_replace(' ', '-', $originalName);
            try {
                Storage::disk('linode')->put($fileName, file_get_contents($file), 'public');
                Log::info('File successfully uploaded to bucket: ' . $fileName);
            } catch (\Exception $e) {
                Log::error('Error uploading file: ' . $e->getMessage());
                return $this->sendError($e->getMessage());
            }

            $url = "https://ap-south-1.linodeobjects.com/kebelet-media" . '/' . $fileName;
            Log::info($url);

            $data = ReviewImages::create([
                'review_id' => $reviewId,
                'url_img' => $url,
            ]);

            $uploadedFiles[] = $data;
        }

        return $this->sendResponse($uploadedFiles, 'Success Upload Images', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reviews/{id}",
     *     operationId="reviewRetrieve",
     *     tags={"Review"},
     *     summary="Review Retrieve",
     *     description="Review Retrieve here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Review not found"),
     * )
     */
    public function show($id)
    {
        $Review = Review::find($id);
        if (is_null($Review)) {
            return $this->sendError('Review not found.');
        }
        return $this->sendResponse($Review, 'Review retrieved successfully.');
    }


    /**
     * @OA\Delete(
     *     path="/api/v1/reviews/{id}",
     *     operationId="reviewDelete",
     *     tags={"Review"},
     *     summary="Review Delete",
     *     description="PrReviewoduct Delete here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review deleted successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Failed to delete the Review."),
     *     @OA\Response(response=404, description="Review not found."),
     * )
     */
    public function destroy($id)
    {
        $record = Review::find($id);
        if (is_null($record)) {
            return $this->sendError('Review not found.');
        }
        $success = $record->delete();
        if (!$success) {
            return $this->sendError('Failed to delete the Review.', 400);
        }
        return $this->sendResponse([], 'Review deleted successfully.');
    }
}
