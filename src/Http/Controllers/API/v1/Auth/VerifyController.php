<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Http\Controllers\API\v1\Auth;

use Ajthenewguy\Php8ApiServer\Http\Controllers\Controller;
use Ajthenewguy\Php8ApiServer\Http\JsonResponse;
use Ajthenewguy\Php8ApiServer\Http\Request;
use Ajthenewguy\Php8ApiServer\Repositories\UserRepository;
use Ajthenewguy\Php8ApiServer\Services\AuthService;

class VerifyController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->contentType() !== 'application/json') {
            return JsonResponse::make(['errors' => ['title' => 'Unsupported Media Type']], 415);
        }

        $validated = $request->validate([
            'verification_code' => ['required', 'exists:users,verification_code'],
            'password' => ['required', 'string', 'regex:/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{12,}$/']
        ], [
            'password.regex' => 'Password must at least 12 characters and include a number.'
        ]);

        return $validated->then(function ($validation) {
            [$data, $errors] = $validation;
            if ($errors) {
                return JsonResponse::make(['errors' => $errors], 401);
            }

            return UserRepository::getForVerification($data['verification_code'])->then(function ($User) use ($data) {
                // if ($User->verified_at === null) {
                    $attributes = [
                        'password' => $data['password'],
                        'verified_at' => new \DateTime()
                    ];
                    return UserRepository::update($User, $attributes)->then(function ($User) {
                        $token = AuthService::createToken($User);

                        return JsonResponse::make([
                            'access_token' => (string) $token,
                            'token_type' => 'Bearer',
                            'expires_in' => $token->claims->exp - time()
                        ]);
                    });
                // } else {
                //     return JsonResponse::make(['errors' => ['authentication' => 'User email already verified.']], 403);
                // }
            });
        });
    }
}