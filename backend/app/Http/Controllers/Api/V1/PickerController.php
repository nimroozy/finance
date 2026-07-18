<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\Product;
use App\Models\Org\Department;
use App\Models\Org\Team;
use App\Models\Services\Service;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PickerController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('customers.view')
            || Auth::user()->can('tickets.view')
            || Auth::user()->can('tickets.create')
            || Auth::user()->can('installations.view')
            || Auth::user()->can('installations.create'),
            403
        );

        $user = Auth::user();
        $query = Customer::query()
            ->select(['id', 'branch_id', 'customer_number', 'contact_name', 'company_name', 'phone', 'mobile', 'whatsapp_number', 'zoho_contact_id', 'status', 'sync_status'])
            ->with('branch:id,code,name_en,name_fa')
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $q->whereIn('branch_id', $user->branchIds());
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')));

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('customer_number', 'like', $search)
                    ->orWhere('contact_name', 'like', $search)
                    ->orWhere('company_name', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('mobile', 'like', $search)
                    ->orWhere('whatsapp_number', 'like', $search)
                    ->orWhere('zoho_contact_id', 'like', $search);
            });
        }

        $items = $query->orderBy('contact_name')->limit($request->integer('limit', 25))->get();

        return ApiResponse::success($items);
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('tickets.assign')
            || Auth::user()->can('tasks.assign')
            || Auth::user()->can('users.view')
            || Auth::user()->can('users.manage')
            || Auth::user()->can('assignments.manage')
            || Auth::user()->can('collectors.view'),
            403
        );

        $user = Auth::user();
        $query = User::query()
            ->active()
            ->select(['id', 'name', 'email', 'username', 'status'])
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $branchIds = $user->branchIds();
                $q->whereHas('branches', fn ($bq) => $bq->whereIn('branches.id', $branchIds));
            })
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->whereHas('branches', fn ($bq) => $bq->where('branches.id', $request->integer('branch_id')));
            })
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('departments', fn ($dq) => $dq->where('departments.id', $request->integer('department_id')));
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->role($request->string('role')->toString());
            });

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('username', 'like', $search);
            });
        }

        $items = $query->orderBy('name')->limit($request->integer('limit', 25))->get();

        return ApiResponse::success($items);
    }

    public function departments(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('tickets.view')
            || Auth::user()->can('tasks.view')
            || Auth::user()->can('tickets.assign')
            || Auth::user()->can('tasks.assign'),
            403
        );

        $user = Auth::user();
        $query = Department::query()
            ->where('is_active', true)
            ->select(['id', 'branch_id', 'code', 'name_en', 'name_fa', 'is_central'])
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->whereIn('branch_id', $user->branchIds())->orWhereNull('branch_id');
                });
            })
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('branch_id', $request->integer('branch_id'))->orWhereNull('branch_id');
                });
            });

        return ApiResponse::success($query->orderBy('code')->limit(100)->get());
    }

    public function teams(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('tickets.view')
            || Auth::user()->can('tasks.view')
            || Auth::user()->can('tickets.assign')
            || Auth::user()->can('tasks.assign'),
            403
        );

        $user = Auth::user();
        $query = Team::query()
            ->where('is_active', true)
            ->select(['id', 'branch_id', 'department_id', 'code', 'name_en', 'name_fa'])
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $q->whereIn('branch_id', $user->branchIds());
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')));

        return ApiResponse::success($query->orderBy('code')->limit(100)->get());
    }

    public function branches(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 403);

        $user = Auth::user();
        $query = Branch::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->select(['id', 'code', 'name_en', 'name_fa', 'is_active']);

        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('id', $user->branchIds());
        }

        return ApiResponse::success($query->orderBy('code')->get());
    }

    public function collectors(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('collectors.view')
            || Auth::user()->can('assignments.manage')
            || Auth::user()->can('customer_ownership.create')
            || Auth::user()->can('customer_ownership.transfer')
            || Auth::user()->can('temporary_assignments.create'),
            403
        );

        $user = Auth::user();
        $query = Collector::query()
            ->select(['id', 'user_id', 'employee_code', 'is_active'])
            ->withCount('activeAssignments')
            ->with(['user:id,name,status', 'user.branches:id,code,name_en,name_fa'])
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $branchIds = $user->branchIds();
                $q->whereHas('user.branches', fn ($bq) => $bq->whereIn('branches.id', $branchIds));
            })
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $branchId = $request->integer('branch_id');
                $q->whereHas('user.branches', fn ($bq) => $bq->where('branches.id', $branchId));
            });

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('employee_code', 'like', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search));
            });
        }

        $items = $query->limit($request->integer('limit', 25))->get();

        return ApiResponse::success($items);
    }

    public function services(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('services.view'), 403);

        $user = Auth::user();
        $query = Service::query()
            ->select(['id', 'service_number', 'customer_id', 'branch_id', 'package_id', 'commercial_status', 'operational_status'])
            ->with(['customer:id,contact_name,company_name', 'package:id,name'])
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $q->whereIn('branch_id', $user->branchIds());
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')));

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('service_number', 'like', $search)
                    ->orWhereHas('customer', fn ($cq) => $cq->where('contact_name', 'like', $search)->orWhere('company_name', 'like', $search));
            });
        }

        $items = $query->orderByDesc('id')->limit($request->integer('limit', 25))->get();

        return ApiResponse::success($items);
    }

    public function equipment(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('inventory.equipment.view'), 403);

        $user = Auth::user();
        $query = Equipment::query()
            ->select(['id', 'equipment_number', 'product_id', 'serial_number', 'mac_address', 'branch_id', 'location_id', 'custodian_user_id', 'status'])
            ->with(['product:id,code,name_en', 'location:id,name', 'custodian:id,name'])
            ->when(! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin(), function ($q) use ($user) {
                $q->whereIn('branch_id', $user->branchIds());
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('serial_number', 'like', $search)
                    ->orWhere('mac_address', 'like', $search)
                    ->orWhere('equipment_number', 'like', $search);
            });
        }

        $items = $query->orderByDesc('id')->limit($request->integer('limit', 25))->get();

        return ApiResponse::success($items);
    }

    public function products(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('inventory.products.view'), 403);

        $query = Product::query()
            ->where('is_active', true)
            ->select(['id', 'code', 'name_en', 'name_fa', 'category_id']);

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', $search)
                    ->orWhere('name_en', 'like', $search);
            });
        }

        $items = $query->orderBy('name_en')->limit($request->integer('limit', 25))->get();

        return ApiResponse::success($items);
    }
}
