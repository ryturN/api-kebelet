<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use App\Models\Toilet;
use App\Models\User;
use App\Models\Review;
use App\Models\ToiletFavorite;
use App\Models\ToiletHours;
use App\Models\ToiletImage;
use App\Models\ToiletVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use function PHPUnit\Framework\isNull;

class ToiletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/toilets",
     *     operationId="toiletList",
     *     tags={"Toilet"},
     *     summary="Toilet List",
     *     description="Toilet List here",
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
     *         name="sort_by",
     *         in="query",
     *         description="Sort By (nearest)",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="lat",
     *         in="query",
     *         description="Latitude",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="long",
     *         in="query",
     *         description="Longitude",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         description="Radius",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Toilet category",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="grade",
     *         in="query",
     *         description="Toilet Grade",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilets retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function index(Request $request)
    {
        // Get user by token
        $userId = NULL;
        $token = $request->bearerToken();
        $token = PersonalAccessToken::findToken($token);
        if ($token) {
            $user = $token->tokenable;
            $userId = $user ? $user->id : null;
        }

        $sortBy = $request->input('sort_by');
        $category = $request->input('category');
        $grade = $request->input('grade');
        if ($request->input('size')) {
            $size = $request->input('size');
        } else {
            // Set default pagination size
            $size = 10;
        }
        $query = Toilet::query();
        if ($sortBy == 'nearest') {
            if (!$request->input('lat') or !$request->input('long')) {
                return $this->sendError('Please input latitude and longitude.');
            }
            $lat = (float)$request->input('lat');
            $lon = (float)$request->input('long');
            $radius = (float)$request->input('radius');
            if (!$request->input('radius')) {
                $radius = (float)30;
            }

            $query->select(
                'id',
                'owner_id',
                'location_id',
                'name',
                'address',
                'grade',
                'latitude',
                'longitude',
                'created_at',
                'updated_at',
                DB::raw('6371 * acos(cos(radians(' . $lat . '))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(' . $lon . '))
                    + sin(radians(' . $lat . '))
                    * sin(radians(latitude))) AS distance')
            )->having('distance', '<=', $radius)
                ->orderBy('distance')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');
            if ($category) {
                $categories = explode(';', $category);
                $query->whereHas('category', function ($query) use ($categories) {
                    $query->whereIn('name', $categories);
                });
            }
            if ($grade) {
                $grades = explode(';', $grade);
                $query->whereIn('grade', $grades);
            }
        }

        if ($category) {
            $categories = explode(';', $category);
            $query->whereHas('category', function ($query) use ($categories) {
                $query->whereIn('name', $categories);
            });
        }
        if ($grade) {
            $grades = explode(';', $grade);
            $query->whereIn('grade', $grades);
        }

        $toilets = $query->with([
            'toiletHours:id,toilet_id,day,hour_open,hour_close',
            'toiletImage:id,toilet_id,url,sequence',
            'favorite:id,user_id,toilet_id'
        ])->paginate($size);

        foreach ($toilets as $toilet) {
            $isFavorite = $toilet->favorite->contains('user_id', $userId);
            $toilet->is_favorite = $isFavorite;
        }

        foreach ($toilets as $toilet) {
            unset($toilet->favorite);
            $sumRating = Review::where('toilet_id', $toilet->id)->sum('rating');
            $count = Review::where('toilet_id', $toilet->id)->count();
            $toilet->rating = $count == 0 ? 0 : $sumRating / $count;

            $toilet->owner = User::select('id', 'username', 'email', 'profile_picture', 'rank')->find($toilet->owner_id);
            $toilet->category = Location::select('id', 'name')->find($toilet->location_id);
        }
        return $this->sendResponse($toilets, 'Toilets retrieved successfully.');
    }


    /**
     * @OA\Post(
     *     path="/api/v1/toilets",
     *     operationId="toiletCreate",
     *     tags={"Toilet"},
     *     summary="Toilet Create",
     *     description="Toilet Create here",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"location_id", "name", "address"},
     *                 @OA\Property(property="location_id", type="number"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="latitude", type="number"),
     *                 @OA\Property(property="longitude", type="number")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Toilet successfully created",
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
            'location_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['numeric', 'between:-90.00,90.00'],
            'longitude' => ['numeric', 'between:-180.00,180.00']
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $user = Auth::user();
        $latitude = 0.0;
        $longitude = 0.0;

        if ($request->input('latitude')) {
            $latitude = $request->input('latitude');
        }
        if ($request->input('longitude')) {
            $longitude = $request->input('longitude');
        }

        $toilet = Toilet::create([
            'owner_id' => $user->id,
            'location_id' => $request->input('location_id'),
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);
        return $this->sendResponse($toilet, 'Toilet successfully created', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/toilets/{id}",
     *     operationId="toiletRetrieve",
     *     tags={"Toilet"},
     *     summary="Toilet Retrieve",
     *     description="Toilet Retrieve here",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Toilet id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Toilet not found"),
     * )
     */
    public function show(Request $request, $id)
    {
        // Get user by token
        $userId = NULL;
        $token = $request->bearerToken();
        $token = PersonalAccessToken::findToken($token);
        if ($token) {
            $user = $token->tokenable;
            $userId = $user ? $user->id : null;
        }

        $toilet = Toilet::with([
            'owner:id,username,email,profile_picture,rank',
            'checkins:id,toilet_id,user_id,created_at',
            'reviews' => function($query) {
                $query->with([
                    'user:id,username,email,profile_picture,rank',
                    'reviewImages:id,review_id,url_img,created_at'
                ])->orderBy('created_at', 'desc');;
            },
            'category:id,name',
            'toiletHours:id,toilet_id,day,hour_open,hour_close',
            'toiletImage:id,toilet_id,url,sequence',
            'favorite:id,user_id,toilet_id'
        ])->find($id);

        if (is_null($toilet)) {
            return $this->sendError('Toilet not found.');
        }

        $reviewCount = $toilet->reviews->count();
        $toilet->rating = $reviewCount ? $toilet->reviews->sum('pivot.rating') / $reviewCount : 0;

        // $totalVisits = $toilet->visitHours->count();
        $totalVisits = $toilet->checkins->count();
        $lastSevenDaysDate = [];

        for ($i = 0; $i < 7; $i++) {
            $sevenDaysAgo = Carbon::now()->subDays($i);
            $sevenDaysAgoString = $sevenDaysAgo->toDateString();
            $lastSevenDaysDate[] = $sevenDaysAgoString;
        }
        $totalCount = [];
        foreach ($lastSevenDaysDate as $index => $day) {
            $date = $lastSevenDaysDate[$index];
            $visit = $toilet->checkins->filter(function ($checkin) use ($date) {
                return $checkin->created_at->toDateString() == $date;
            });
            $totalCount[$day] = $visit->count();
        }
        $currentDate = Carbon::now()->toDateString();
        $currentDayCheckins = $toilet->checkins->filter(function ($checkin) use ($currentDate) {
            return $checkin->created_at->toDateString() == $currentDate;
        });
        date_default_timezone_set('Asia/Jakarta');
        $time = strtotime(now()) - strtotime(date('Y-m-d 00:00:00'));
        $time = round(abs($time) / 3600, 2);

        $visitorsPerHour = $currentDayCheckins->count() / $time;

        $visitPerHour = [
            'visit' => $currentDayCheckins->count(),
            'time' => $time,
            'visitors_per_hour' => $visitorsPerHour
        ];
        $isFavorite = $toilet->favorite->contains('user_id', $userId);
        $toilet->is_favorite = $isFavorite;
        $toilet->next_toilet = $id + 1;
        $toilet->visit_per_hour = $visitPerHour;
        $toilet->last_visit_7days = $totalCount;
        $toilet->reviews = $toilet->reviews->sortByDesc('created_at')->values();
        $toilet->reviews->transform(function ($review) {
            $review->total_toilet = explode(',', $review->total_toilet);
            $review->cleanness = explode(',', $review->cleanness);
            $review->facility = explode(',', $review->facility);
            return $review;
        });
        unset($toilet->checkins);
        unset($toilet->favorite);

        return $this->sendResponse($toilet, 'Toilet retrieved successfully.');
    }
    /**
     * @OA\Put(
     *     path="/api/v1/toilets/{id}",
     *     operationId="ToiletUpdate",
     *     tags={"Toilet"},
     *     summary="Toilet Update",
     *     description="Toilet Update here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Toilet id",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *         name="location_id",
     *         in="query",
     *         description="Location id",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Toilet name",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="address",
     *         in="query",
     *         description="Toilet address",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         description="Toilet latitude",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         description="Toilet longitude",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet updated successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Validation Error."),
     *     @OA\Response(response=409, description="Failed to update the Toilet."),
     *     @OA\Response(response=404, description="Toilet not found."),
     * )
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'location_id' => ['integer'],
            'name' => ['string', 'min:3', 'max:255'],
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['numeric', 'between:-90.00,90.00'],
            'longitude' => ['numeric', 'between:-180.00,180.00']
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }
        $record = Toilet::find($id);
        if (is_null($record)) {
            return $this->sendError('Toilet not found.');
        }
        $success = $record->update($input);
        if (!$success) {
            return $this->sendError('Failed to update the Toilet.', 400);
        }
        return $this->sendResponse($record, 'Toilet updated successfully.');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/toilets/{id}",
     *     operationId="toiletDelete",
     *     tags={"Toilet"},
     *     summary="Toilet Delete",
     *     description="PrToiletoduct Delete here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Toilet id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet deleted successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Failed to delete the Toilet."),
     *     @OA\Response(response=404, description="Toilet not found."),
     * )
     */
    public function destroy($id)
    {
        $record = Toilet::find($id);
        if (is_null($record)) {
            return $this->sendError('Toilet not found.');
        }
        $success = $record->delete();
        if (!$success) {
            return $this->sendError('Failed to delete the Toilet.', 400);
        }
        return $this->sendResponse([], 'Toilet deleted successfully.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/toilets-favorite",
     *     operationId="favorite",
     *     tags={"Toilet"},
     *     summary="Toilet favorite",
     *     description="Toilet Favorite",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="size",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="lat",
     *         in="query",
     *         description="Latitude",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="long",
     *         in="query",
     *         description="Longitude",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Toilet not found"),
     * )
     */
    public function indexFavorite(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;
        $userLat = $request->input('lat');
        $userLon = $request->input('long');

        if (!$userLat || !$userLon) {
            return $this->sendError('Please provide latitude and longitude.');
        }

        $size = $request->input('size', 10);

        $favorites = ToiletFavorite::where('user_id', $userId)
            ->with(['toilet' => function ($query) use ($userLat, $userLon) {
                $query->select(
                    'toilets.*',
                    DB::raw('6371 * acos(cos(radians(' . $userLat . '))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(' . $userLon . '))
                    + sin(radians(' . $userLat . '))
                    * sin(radians(latitude))) AS distance')
                )->orderBy('distance');
            }])
            ->paginate($size);

        foreach ($favorites as $favorite) {
            if ($favorite->toilet) {
                $favorite->toilet->is_favorite = true;
                $sumRating = Review::where('toilet_id', $favorite->toilet->id)->sum('rating');
                $count = Review::where('toilet_id', $favorite->toilet->id)->count();
                $favorite->toilet->rating = $count == 0 ? 0 : $sumRating / $count;
                unset($favorite->toilet->checkins);
                unset($favorite->toilet->reviews);
            }
        }

        $toilets = $favorites->getCollection()->map(function ($favorite) {
            return $favorite->toilet;
        });

        $favorites->setCollection($toilets);
        return $this->sendResponse($favorites, 'Toilets retrieved successfully');
    }


    /**
     * @OA\Post(
     *     path="/api/v1/toilets-favorite/{id}",
     *     operationId="favorite toilet",
     *     tags={"Toilet"},
     *     summary="Toilet favorite",
     *     description="Toilet Favorite",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Toilet id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Toilet not found"),
     * )
     */
    public function favorite($id)
    {
        $userId = Auth::user()->id;
        $isFavorite = ToiletFavorite::where('user_id', $userId)
            ->where('toilet_id', $id)
            ->first();

        if ($isFavorite) {
            return $this->sendError('Toilet already favorited.');
        }
        $toilet = ToiletFavorite::create([
            'toilet_id' => $id,
            'user_id' => $userId
        ]);

        return $this->sendResponse($toilet, 'Toilet favorited successfully.');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/toilets-favorite/{id}",
     *     operationId="unfavorite-toilet",
     *     tags={"Toilet"},
     *     summary="Toilet favorite",
     *     description="Toilet Favorite",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Toilet id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Toilet not found"),
     * )
     */
    public function unfavorite($id)
    {
        $userId = Auth::user()->id;
        $isFavorite = ToiletFavorite::where('user_id', $userId)
            ->where('toilet_id', $id)
            ->first();

        if (!$isFavorite) {
            return $this->sendError('Toilet not favorited.');
        }

        $isFavorite->delete();
        return $this->sendResponse([], 'Toilet removed from favorites');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/toilets/images",
     *     operationId="Store Toilet Image",
     *     tags={"Toilet"},
     *     summary="Store a new toilet image",
     *     description="Store Toilet Image here",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"toilet_id", "img"},
     *                    @OA\Property(property="toilet_id", type="integer"),
     *                    @OA\Property(property="img", type="file", format="binary"),
     *                    @OA\Property(property="sequence", type="integer")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image uploaded successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function storeImage(Request $request)
    {
        $validate = validator::make($request->all(), [
            'toilet_id' => 'required',
            'img' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'sequence' => 'nullable'
        ]);
        if ($validate->failed()) {
            return $this->sendError('Validation Error.', $validate->errors(), 400);
        }
        $url = NULL;
        if ($request->img) {
            $file = $request->file('img');
            $originalName = $file->getClientOriginalName();
            $fileName = 'reviews/' . time() . '-' . str_replace(' ', '-', $originalName);
            try {
                Storage::disk('linode')->put($fileName, file_get_contents($file), 'public');
                Log::info('File successfully uploaded to bucket: ' . $fileName);
            } catch (\Exception $e) {
                Log::error('Error Upload File' . $e->getMessage());
                return $this->sendError($e->getMessage());
            }
            $url = "https://ap-south-1.linodeobjects.com/kebelet-media". '/' . $fileName;
            log::info($url);
        }
        $toilet = ToiletImage::create([
            'toilet_id' => $request->toilet_id,
            'url' => $url,
            'sequence' => $request->get('sequence') ?? 1,
        ]);
        return $this->sendResponse($toilet, 'Image uploaded successfully.', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/toilets/update-images/{id}",
     *     operationId="Update Toilet Image",
     *     tags={"Toilet"},
     *     summary="Update  toilet image",
     *     description="Update Toilet Image here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Toilet image id",
     *         required=true,
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                    @OA\Property(property="img", type="file", format="binary"),
     *                    @OA\Property(property="sequence", type="integer")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image uploaded successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateImage(Request $request, $id)
    {
        $image = ToiletImage::find($id);
        if (is_null($image)) {
            return $this->sendError('Image not found.');
        }
        $validate = validator::make($request->all(), [
            'img' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);
        if ($validate->failed()) {
            return $this->sendError('Validation Error.', $validate->errors(), 400);
        }
        $url = NULL;
        if ($request->img) {
            $file = $request->file('img');
            $originalName = $file->getClientOriginalName();
            $fileName = 'reviews/' . time() . '-' . str_replace(' ', '-', $originalName);
            try {
                Storage::disk('linode')->put($fileName, file_get_contents($file), 'public');
                Log::info('File successfully uploaded to bucket: ' . $fileName);
            } catch (\Exception $e) {
                Log::error('Error Upload File' . $e->getMessage());
                return $this->sendError($e->getMessage());
            }
            $url = "https://ap-south-1.linodeobjects.com/kebelet-media" . '/' . $fileName;
            log::info($url);
            $image->url = $url;
        }
        if (!is_null($request->get('sequence'))) {
            $image->sequence = $request->get('sequence');
        }
        $image->save();
        return $this->sendResponse($image, 'Image updated successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/toilets/images/{id}",
     *     operationId="delete toilet image",
     *     tags={"Toilet"},
     *     summary="Delte Toilet Image",
     *     description="Delete Toilet Image",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Image id",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toilet delete successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=404, description="Toilet not found"),
     * )
     */
    public function deleteImage($id)
    {
        $image = ToiletImage::find($id);
        if (is_null($image)) {
            return $this->sendError('Image not found.');
        }
        try {
            Storage::disk('linode')->delete(str_replace(env('LINODE_ENDPOINT') . '/' . env('LINODE_BUCKET') . '/', '', $image->url));
            Log::info('File successfully deleted from bucket: ' . $image->url);
        } catch (\Exception $e) {
            Log::error('Error Deleting File' . $e->getMessage());
        }

        $image->delete();
        return $this->sendResponse('Image deleted successfully.', 200);
    }
}
