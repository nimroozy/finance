<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles', 'branches'])
            ->when(! $request->user()->isSuperAdmin() && ! $request->user()->isCentralFinanceAdmin(), function ($q) use ($request) {
                $branchIds = $request->user()->branchIds();
                $q->whereHas('branches', fn ($bq) => $bq->whereIn('branches.id', $branchIds));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success(
            $users->getCollection()->map(fn (User $user) => $this->transform($user))->values(),
            [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['roles', 'branch_ids']);
            $data['force_password_change'] = $request->boolean('force_password_change', true);
            $data['status'] = User::STATUS_ACTIVE;

            $user = User::query()->create($data);

            if ($request->filled('roles')) {
                $this->assertAssignableRoles($request->user(), $request->input('roles'));
                $user->syncRoles($request->input('roles'));
            }

            if ($request->filled('branch_ids')) {
                $user->branches()->sync($request->input('branch_ids'));
            }

            return $user->load(['roles', 'branches']);
        });

        $this->auditLogger->log('user.created', $user, null, $user->only([
            'id', 'name', 'email', 'username', 'status',
        ]));

        return ApiResponse::success($this->transform($user), null, 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'branches']);

        return ApiResponse::success($this->transform($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $old = $user->only(['name', 'email', 'username', 'phone', 'locale', 'status']);

        DB::transaction(function () use ($request, $user) {
            $data = $request->safe()->except(['roles', 'branch_ids']);
            $wasDisabled = $user->status !== User::STATUS_DISABLED
                && (($data['status'] ?? null) === User::STATUS_DISABLED);

            $user->fill($data)->save();

            if ($request->has('roles')) {
                $roles = $request->input('roles', []);
                $this->assertAssignableRoles($request->user(), $roles);
                $user->syncRoles($roles);
            }

            if ($request->has('branch_ids')) {
                $user->branches()->sync($request->input('branch_ids', []));
            }

            if ($wasDisabled) {
                $user->tokens()->delete();
            }
        });

        $user->load(['roles', 'branches']);

        $this->auditLogger->log('user.updated', $user, $old, $user->only([
            'name', 'email', 'username', 'phone', 'locale', 'status',
        ]));

        return ApiResponse::success($this->transform($user));
    }

    public function disable(User $user): JsonResponse
    {
        $this->authorize('disable', $user);

        $old = ['status' => $user->status];
        $user->forceFill(['status' => User::STATUS_DISABLED])->save();
        $user->tokens()->delete();

        $this->auditLogger->log('user.disabled', $user, $old, ['status' => $user->status]);

        return ApiResponse::success($this->transform($user->load(['roles', 'branches'])));
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'status' => $user->status,
            'force_password_change' => (bool) $user->force_password_change,
            'last_login_at' => $user->last_login_at,
            'roles' => $user->relationLoaded('roles')
                ? $user->getRoleNames()->values()
                : [],
            'branches' => $user->relationLoaded('branches')
                ? $user->branches->map(fn ($b) => [
                    'id' => $b->id,
                    'code' => $b->code,
                    'name_en' => $b->name_en,
                ])->values()
                : [],
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    protected function assertAssignableRoles(User $actor, array $roles): void
    {
        if (in_array(User::ROLE_SUPER_ADMIN, $roles, true) && ! $actor->isSuperAdmin()) {
            abort(403, 'Only Super Administrators may assign the Super Administrator role.');
        }
    }
}
