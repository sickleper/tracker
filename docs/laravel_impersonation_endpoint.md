# Laravel Impersonation Endpoint

This tracker app now expects a real backend-driven impersonation flow.

The tracker-side code calls:

- `POST /api/admin/impersonate`
- `POST /api/admin/stop-impersonation` (optional, but recommended)

The backend must issue a real token for the impersonated user. A local PHP session swap in the tracker is not sufficient, because tracker API authorization is bearer-token based.

## Expected Request

`POST /api/admin/impersonate`

```json
{
  "tenant_id": 12,
  "tenant_slug": "acme",
  "user_id": 455
}
```

Requirements:

- Caller must already be authenticated
- Caller must be the global superadmin
- Target user must belong to the requested tenant
- Response must include a usable auth payload with a token for the impersonated user

## Expected Response

```json
{
  "success": true,
  "message": "Impersonation started.",
  "data": {
    "token": "plain-text-token",
    "user_id": 455,
    "user_auth_id": 455,
    "tenant_id": 12,
    "tenant_slug": "acme",
    "role_id": null,
    "is_office": true,
    "name": "Jane Office",
    "email": "jane@example.com",
    "google_id": null
  }
}
```

Minimum required fields for the tracker:

- `token`
- `user_id`
- `tenant_id`
- `tenant_slug`
- `is_office`
- `name`
- `email`

## Route Example

Add this in Laravel `routes/api.php`:

```php
use App\Http\Controllers\Api\AdminImpersonationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/impersonate', [AdminImpersonationController::class, 'impersonate']);
    Route::post('/admin/stop-impersonation', [AdminImpersonationController::class, 'stop']);
});
```

## Controller Example

Create `app/Http/Controllers/Api/AdminImpersonationController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminImpersonationController extends Controller
{
    public function impersonate(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (!$actor || $actor->email !== config('app.super_admin_email')) {
            return response()->json([
                'success' => false,
                'message' => 'Only superadmin may impersonate users.',
            ], 403);
        }

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'min:1'],
            'tenant_slug' => ['nullable', 'string'],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $tenant = Tenant::query()->find($data['tenant_id']);
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found.',
            ], 404);
        }

        if (!empty($data['tenant_slug']) && $tenant->slug !== $data['tenant_slug']) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant mismatch.',
            ], 422);
        }

        $user = User::query()
            ->where('id', $data['user_id'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in that tenant.',
            ], 404);
        }

        DB::table('admin_impersonation_audit')->insert([
            'actor_user_id' => $actor->id,
            'actor_email' => $actor->email,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'impersonated_user_id' => $user->id,
            'impersonated_email' => $user->email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenName = 'tracker-impersonation';
        $plainTextToken = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Impersonation started.',
            'data' => [
                'token' => $plainTextToken,
                'user_id' => $user->id,
                'user_auth_id' => $user->id,
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'role_id' => $user->role_id,
                'is_office' => (bool) $user->is_office,
                'name' => $user->name,
                'email' => $user->email,
                'google_id' => $user->google_id,
            ],
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tracker restores the original superadmin session locally.',
        ]);
    }
}
```

## Config Example

Add a stable superadmin config value in Laravel, for example in `config/app.php`:

```php
'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'websites.dublin@gmail.com'),
```

## Audit Table Example

Recommended migration:

```php
Schema::create('admin_impersonation_audit', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('actor_user_id')->nullable();
    $table->string('actor_email')->nullable();
    $table->unsignedBigInteger('tenant_id');
    $table->string('tenant_slug');
    $table->unsignedBigInteger('impersonated_user_id');
    $table->string('impersonated_email')->nullable();
    $table->timestamps();
});
```

## Notes

- Do not reuse the superadmin token during impersonation.
- Create a fresh token for the target user.
- Keep the original superadmin session in the tracker app so `Stop Impersonating` can restore it.
- If your user model has a different token system, return the equivalent bearer token in the same response shape.
