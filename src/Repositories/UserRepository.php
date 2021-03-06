<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Repositories;

use Ajthenewguy\Php8ApiServer\Models\User;
use Ajthenewguy\Php8ApiServer\Services\AuthService;
use React\Promise;

class UserRepository
{
    public static function getById(int|string $id): Promise\PromiseInterface
    {
        return User::find($id);
    }

    public static function getForLogin(string $value, string $field = 'email'): Promise\PromiseInterface
    {
        return User::where($field, $value)->first();
    }

    public static function getForVerification(string $value, string $field = 'verification_code'): Promise\PromiseInterface
    {
        return User::where($field, $value)->first();
    }

    public static function create(array $attributes): Promise\PromiseInterface
    {
        $attributes['verification_code'] = User::generateVerificationCode();

        return User::create($attributes);
    }

    public static function update(User $User, array $attributes): Promise\PromiseInterface
    {
        if (isset($attributes['password'])) {
            $attributes['password'] = AuthService::hash($attributes['password']);
        }
        
        return $User->update($attributes);
    }

    public static function delete(int|string $id): Promise\PromiseInterface
    {
        return User::find($id)->then(function (User $User) {
            return $User->delete();
        });
    }
}