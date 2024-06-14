<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FacebookLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Auth\GoogleLoginRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use stdClass;

class AuthenticatedSessionController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/v1/login",
     * operationId="authLogin",
     * tags={"Authentication"},
     * summary="User Login",
     * description="Login User here",
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"email", "password"},
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="password", type="string")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login Successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     * )
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // return response()->noContent();

        $user = auth()->user();
        $response = new stdClass();
        $response->user = $user;
        $response->token = $user->createToken('authToken')->plainTextToken;

        return $this->sendResponse($response, 'Login Successfully', 200);
    }

    /**
     * @OA\Post(
     * path="/api/v1/google_signin",
     * operationId="googleSignIn",
     * tags={"Authentication"},
     * summary="User Google Sign In",
     * description="User Google Sign In here",
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"email", "role", "google_id"},
     *                 @OA\Property(property="email", type="text"),
     *                 @OA\Property(property="role", type="text"),
     *                 @OA\Property(property="google_id", type="text"),
     *                 @OA\Property(property="profile_picture", type="text"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil Login",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Tidak Ditemukan"),
     * )
     */
    public function google_signin(Request $request, GoogleLoginRequest $loginrequest)
    {
        $input = $request->all();
        Validator::extend('without_spaces', function ($attr, $value) {
            return preg_match('/^\S*$/u', $value);
        });
        $validator = Validator::make($input, [
            'email' => ['required', 'email:rfc,dns', 'min:1', 'max:100'],
            'google_id' => ['required', 'string', 'min:1', 'max:255'],
            'role' => ['required', 'in:user,admin,superadmin']
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $loginrequest->ensureIsNotRateLimited();
        $check_email = User::where('email', $request->email);
        if (is_null($check_email->first())) {
            $password = $request->email;
            $user = User::create([
                'username' => explode('@', $request->email)[0],
                'email' => $request->email,
                'password' => Hash::make($password),
                'role' => ucfirst($request->role),
                'profile_picture' => $request->profile_picture,
                'google_id' => $request->google_id
            ]);
            Auth::login($user);

            $response = new stdClass();
            $response->message = 'Register sukses';
            $response->data = $user;
            $response->token = $user->createToken('authToken')->plainTextToken;
            $response->status = 201;
            return response()->json($response, 201);
        } else {
            $check_emails = User::where('email', $request->email)->where('google_id', $request->google_id);
            if (is_null($check_emails->first())) {
                $check_email = $check_email->first();
                $check_email->google_id = $request->google_id;
                $check_email->save();

                Auth::login($check_email);
                $loginrequest->authenticate();
                $loginrequest->session()->regenerate();


                $response = new stdClass();
                $response->message = 'Berhasil Login via Googles';
                $response->data = $check_email;
                $response->token = $check_email->createToken('authToken')->plainTextToken;
                $response->status = 200;
                return response()->json($response, 200);
            }

            $user = $check_email->where('role', $request->role)->first();
            if (is_null($user)) {
                throw ValidationException::withMessages([
                    'failed' => 'Invalid email / password',
                ]);
            }
            Auth::login($user);
            $loginrequest->authenticate();
            $loginrequest->session()->regenerate();


            $response = new stdClass();
            $response->message = 'Berhasil Login via Google';
            $response->data = $user;
            $response->token = $user->createToken('authToken')->plainTextToken;
            $response->status = 200;
            return response()->json($response, 200);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/facebook_signin",
     * operationId="facebookSignIn",
     * tags={"Authentication"},
     * summary="User Facebook Sign In",
     * description="User Facebook Sign In here",
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"email", "role", "facebook_id"},
     *                 @OA\Property(property="email", type="text"),
     *                 @OA\Property(property="role", type="text"),
     *                 @OA\Property(property="facebook_id", type="text"),
     *                 @OA\Property(property="profile_picture", type="text"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil Login",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Tidak Ditemukan"),
     * )
     */
    public function facebook_signin(Request $request, FacebookLoginRequest $loginrequest)
    {
        $input = $request->all();
        Validator::extend('without_spaces', function ($attr, $value) {
            return preg_match('/^\S*$/u', $value);
        });
        $validator = Validator::make($input, [
            'email' => ['required', 'email:rfc,dns', 'min:1', 'max:100'],
            'facebook_id' => ['required', 'string', 'min:1', 'max:255'],
            'role' => ['required', 'in:user,admin,superadmin']
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }
        $loginrequest->ensureIsNotRateLimited();
        $check_email = User::where('email', $request->email);
        if (is_null($check_email->first())) {
            $password = $request->email;
            $user = User::create([
                'username' => explode('@', $request->email)[0],
                'email' => $request->email,
                'password' => Hash::make($password),
                'role' => ucfirst($request->role),
                'facebook_id' => $request->facebook_id,
                'profile_picture' => $request->profile_picture,
            ]);
            Auth::login($user);
            $response = new stdClass();
            $response->message = 'Register sukses';
            $response->data = $user;
            $response->token = $user->createToken('authToken')->plainTextToken;
            $response->status = 201;
            return response()->json($response, 201);
        } else {
            $check_emails = User::where('email', $request->email)->where('facebook_id', $request->facebook_id);
            if (is_null($check_emails->first())) {
                $check_email = $check_email->first();
                $check_email->facebook_id = $request->facebook_id;
                $check_email->save();

                Auth::login($check_email);
                $loginrequest->authenticate();
                $loginrequest->session()->regenerate();


                $response = new stdClass();
                $response->message = 'Berhasil Login via Facebook';
                $response->data = $check_email;
                $response->token = $check_email->createToken('authToken')->plainTextToken;
                $response->status = 200;
                return response()->json($response, 200);
            }
            $user = $check_email->where('role', $request->role)->first();
            if (is_null($user)) {
                throw ValidationException::withMessages([
                    'failed' => 'Invalid email / password',
                ]);
            }
            Auth::login($user);
            $loginrequest->authenticate();
            $loginrequest->session()->regenerate();
            $response = new stdClass();
            $response->message = 'Berhasil Login via Facebook';
            $response->data = $user;
            $response->token = $user->createToken('authToken')->plainTextToken;
            $response->status = 200;
            return response()->json($response, 200);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/logout",
     * operationId="authLogout",
     * tags={"Authentication"},
     * summary="User Logout",
     * security={{"sanctum":{}}},
     * description="Logout User here",
     *     @OA\Response(
     *         response=200,
     *         description="Logout Successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     * )
     */
    public function destroy(Request $request): JsonResponse
    {
        // Auth::guard('web')->logout();

        // $request->session()->invalidate();

        // $request->session()->regenerateToken();

        // return response()->noContent();

        Auth::Logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->sendResponse(NULL, 'Logout Successfully', 200);
    }
}
