<?php

namespace App\Http\Controllers;

use App\Models\PointHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PointController extends Controller
{
    //
    /**
     * @OA\Get(
     *     path="/api/v1/point",
     *     operationId="Leaderboard",
     *     tags={"Point"},
     *     summary="Leaderboard",
     *     description="Leaderboard here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="size",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="page",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="filter sort by",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="filter sort order",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfuly retrieved leaderboard",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function index(Request $request)
    {
        $size = $request->get('size', 10);
        $page = $request->get('page', 1);

        $allowedSortColumns = ['id', 'username', 'point'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'point';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $users = User::orderBy($sortBy, $sortOrder)
            ->orderBy('updated_at', 'asc')
            ->select('id', 'username', 'point','profile_picture')
            ->paginate($size);

        $userId = Auth::user()->id;
        $loggedInUser = User::find($userId);
        if ($loggedInUser) {
            $loggedInUserPoint = $loggedInUser->point;
            $position = User::where('point', '>', $loggedInUserPoint)
                ->orderBy('point', 'desc')
                ->orderBy('updated_at', 'asc')
                ->count() + 1;

            $loggedInUserPositionData = [
                'position' => $position,
                'username' => $loggedInUser->username,
                'point' => $loggedInUser->point,
            ];
        }

        $position = ($page - 1) * $size + 1;
        $usersWithPosition = $users->map(function ($user) use (&$position) {
            return [
                'position' => $position++,
                'username' => $user->username,
                'profile_picture' => $user->profile_picture,
                'point' => $user->point,
            ];
        });

        $response = [
            'leaderboard' => [
                'current_page' => $users->currentPage(),
                'data' => $usersWithPosition,
                'loggedInUserPosition' => $loggedInUserPositionData,
                'first_page_url' => $users->url(1),
                'from' => $users->firstItem(),
                'last_page' => $users->lastPage(),
                'last_page_url' => $users->url($users->lastPage()),
                'links' => $users->linkCollection(),
                'next_page_url' => $users->nextPageUrl(),
                'path' => $users->path(),
                'per_page' => $users->perPage(),
                'prev_page_url' => $users->previousPageUrl(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
        ];


        return $this->sendResponse($response, 'Successfully retrieved leaderboard', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/point",
     *     operationId="Claim Point",
     *     tags={"Point"},
     *     summary="Claim Point",
     *     description="Claim Point here",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfuly claim token",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function claimPoint()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->sendError('Unauthorized', 401);
        }

        $now = Carbon::now();
        $lastClaimed = $user->last_claimed_at;

        if ($lastClaimed && $now->diffInDays($lastClaimed) < 1) {
            return $this->sendError('You can only claim points once a day', 403);
        }

        DB::transaction(function () use ($user, $now) {
            $user->lockForUpdate();
            $user->point += 10;
            $user->last_claimed_at = $now;
            $user->save();

            PointHistory::create([
                'user_id' => $user->id,
                'points' => 10,
                'type' => 'claim',
                'occurred_at' => $now,
            ]);
        });

        return $this->sendResponse(['point' => $user->point], 'Successfully updated points', 200);
    }
    /**
     * @OA\Delete(
     *     path="/api/v1/point/{id}",
     *     operationId="Delete Point",
     *     tags={"Point"},
     *     summary="Delete Point",
     *     description="Delete Point here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="user_id",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *         name="points",
     *         in="query",
     *         description="Number of points to delete",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted points",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function deletePoint(Request $request, $id)
    {
        $auth = Auth::user();
        if ($auth->role != 'admin') {
            return $this->sendError('Unauthorized', 400);
        }
        $user = User::find($id);

        $pointsToDelete = $request->get('points', 0);
        if ($pointsToDelete <= 0) {
            return $this->sendError('Invalid number of points', 400);
        }

        DB::transaction(function () use ($user, $pointsToDelete) {
            $user->lockForUpdate();
            if ($user->point < $pointsToDelete) {
                return $this->sendError('Insufficient points', 403);
            }
            $user->point -= $pointsToDelete;
            $user->save();

            PointHistory::create([
                'user_id' => $user->id,
                'points' => -$pointsToDelete,
                'type' => 'deduction',
                'occurred_at' => Carbon::now(),
            ]);
        });

        return $this->sendResponse(['point' => $user->point], 'Successfully deleted points', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/point-histories",
     *     operationId="Histories point",
     *     tags={"Point"},
     *     summary="Histories point",
     *     description="Histories point here",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="size",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="page",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="filter user_id",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfuly retrieved leaderboard",
     *         @OA\JsonContent()
     *     ),
     * )
     */
    public function pointHistory(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return $this->sendError('Unauthorized', 401);
        }
        $query = PointHistory::query();

        $size = $request->size ?? 10;
        $page = $request->page;
        $userId = $request->get('user_id', $authUser->id);

        if ($authUser->role != 'admin') {
            return $this->sendError('Unauthorized', 403);
        }
        if ($request->user_id) {
            $query->where('user_id', $userId);
        }
        $history = $query->orderBy('occurred_at', 'desc')
            ->paginate($size);

        return $this->sendResponse($history, 'Successfully retrieved point history', 200);
    }
}
