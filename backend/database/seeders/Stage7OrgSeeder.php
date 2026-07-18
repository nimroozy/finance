<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Org\Department;
use App\Models\Org\Team;
use Illuminate\Database\Seeder;

class Stage7OrgSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name_en: string, name_fa: string}>
     */
    public const DEPARTMENTS = [
        ['code' => 'sales', 'name_en' => 'Sales', 'name_fa' => 'فروش'],
        ['code' => 'finance', 'name_en' => 'Finance', 'name_fa' => 'مالی'],
        ['code' => 'technical', 'name_en' => 'Technical', 'name_fa' => 'تخنیکی'],
        ['code' => 'noc', 'name_en' => 'NOC', 'name_fa' => 'مرکز عملیات شبکه'],
        ['code' => 'inventory', 'name_en' => 'Inventory', 'name_fa' => 'انبار'],
        ['code' => 'management', 'name_en' => 'Management', 'name_fa' => 'مدیریت'],
        ['code' => 'customer_support', 'name_en' => 'Customer Support', 'name_fa' => 'پشتیبانی مشتری'],
        ['code' => 'installation', 'name_en' => 'Installation', 'name_fa' => 'نصب'],
    ];

    /** Department codes that get a default team. */
    public const DEFAULTED_TEAM_DEPARTMENTS = ['technical', 'noc', 'installation'];

    public function run(): void
    {
        Branch::query()->withoutGlobalScopes()->each(function (Branch $branch): void {
            foreach (self::DEPARTMENTS as $dept) {
                $department = Department::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'code' => $dept['code'],
                    ],
                    [
                        'name_en' => $dept['name_en'],
                        'name_fa' => $dept['name_fa'],
                        'is_central' => false,
                        'is_active' => true,
                    ]
                );

                if (in_array($dept['code'], self::DEFAULTED_TEAM_DEPARTMENTS, true)) {
                    Team::query()->updateOrCreate(
                        [
                            'department_id' => $department->id,
                            'code' => 'default',
                        ],
                        [
                            'branch_id' => $branch->id,
                            'name_en' => $dept['name_en'].' Default Team',
                            'name_fa' => 'تیم پیش‌فرض '.$dept['name_fa'],
                            'is_active' => true,
                        ]
                    );
                }
            }
        });
    }
}
