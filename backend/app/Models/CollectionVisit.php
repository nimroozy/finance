<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Scopes\CollectorOwnedScope;
use Database\Factories\CollectionVisitFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ScopedBy([BelongsToBranchScope::class, CollectorOwnedScope::class])]
class CollectionVisit extends Model
{
    /** @use HasFactory<CollectionVisitFactory> */
    use HasFactory;

    public const OUTCOME_CUSTOMER_MET = 'customer_met';

    public const OUTCOME_NOT_AVAILABLE = 'not_available';

    public const OUTCOME_NOT_HOME = 'not_home';

    public const OUTCOME_NO_ANSWER = 'no_answer';

    public const OUTCOME_PHONE_UNREACHABLE = 'phone_unreachable';

    public const OUTCOME_WRONG_ADDRESS = 'wrong_address';

    public const OUTCOME_CUSTOMER_MOVED = 'customer_moved';

    public const OUTCOME_REFUSED = 'refused';

    public const OUTCOME_DISPUTED_BALANCE = 'disputed_balance';

    public const OUTCOME_PROMISE_TO_PAY = 'promise_to_pay';

    public const OUTCOME_REQUESTED_MANAGER = 'requested_manager';

    public const OUTCOME_REQUESTED_CANCELLATION = 'requested_cancellation';

    public const OUTCOME_REQUESTED_REVIEW = 'requested_review';

    public const OUTCOME_BUSINESS_CLOSED = 'business_closed';

    public const OUTCOME_FOLLOW_UP = 'follow_up';

    public const OUTCOME_OTHER = 'other';

    public const OUTCOMES = [
        self::OUTCOME_CUSTOMER_MET => ['code' => self::OUTCOME_CUSTOMER_MET, 'label_en' => 'Customer Met', 'label_fa' => 'ملاقات با مشتری'],
        self::OUTCOME_NOT_AVAILABLE => ['code' => self::OUTCOME_NOT_AVAILABLE, 'label_en' => 'Customer Not Available', 'label_fa' => 'مشتری در دسترس نبود'],
        self::OUTCOME_NOT_HOME => ['code' => self::OUTCOME_NOT_HOME, 'label_en' => 'Not Home', 'label_fa' => 'در منزل نبود'],
        self::OUTCOME_NO_ANSWER => ['code' => self::OUTCOME_NO_ANSWER, 'label_en' => 'No Answer', 'label_fa' => 'پاسخ نداد'],
        self::OUTCOME_PHONE_UNREACHABLE => ['code' => self::OUTCOME_PHONE_UNREACHABLE, 'label_en' => 'Phone Unreachable', 'label_fa' => 'تلفن در دسترس نیست'],
        self::OUTCOME_WRONG_ADDRESS => ['code' => self::OUTCOME_WRONG_ADDRESS, 'label_en' => 'Address Incorrect', 'label_fa' => 'آدرس نادرست'],
        self::OUTCOME_CUSTOMER_MOVED => ['code' => self::OUTCOME_CUSTOMER_MOVED, 'label_en' => 'Customer Moved', 'label_fa' => 'مشتری جابه‌جا شده'],
        self::OUTCOME_REFUSED => ['code' => self::OUTCOME_REFUSED, 'label_en' => 'Customer Refused Payment', 'label_fa' => 'امتناع از پرداخت'],
        self::OUTCOME_DISPUTED_BALANCE => ['code' => self::OUTCOME_DISPUTED_BALANCE, 'label_en' => 'Customer Disputed Balance', 'label_fa' => 'اختلاف در مانده'],
        self::OUTCOME_PROMISE_TO_PAY => ['code' => self::OUTCOME_PROMISE_TO_PAY, 'label_en' => 'Promise to Pay', 'label_fa' => 'وعده پرداخت'],
        self::OUTCOME_REQUESTED_MANAGER => ['code' => self::OUTCOME_REQUESTED_MANAGER, 'label_en' => 'Requested Manager', 'label_fa' => 'درخواست مدیر'],
        self::OUTCOME_REQUESTED_CANCELLATION => ['code' => self::OUTCOME_REQUESTED_CANCELLATION, 'label_en' => 'Requested Service Cancellation', 'label_fa' => 'درخواست لغو سرویس'],
        self::OUTCOME_REQUESTED_REVIEW => ['code' => self::OUTCOME_REQUESTED_REVIEW, 'label_en' => 'Requested Account Review', 'label_fa' => 'درخواست بررسی حساب'],
        self::OUTCOME_BUSINESS_CLOSED => ['code' => self::OUTCOME_BUSINESS_CLOSED, 'label_en' => 'Business Closed', 'label_fa' => 'مکان تعطیل'],
        self::OUTCOME_FOLLOW_UP => ['code' => self::OUTCOME_FOLLOW_UP, 'label_en' => 'Follow-up Required', 'label_fa' => 'نیاز به پیگیری'],
        self::OUTCOME_OTHER => ['code' => self::OUTCOME_OTHER, 'label_en' => 'Other', 'label_fa' => 'سایر'],
    ];

    /** Outcomes that require a non-empty notes field. */
    public const NOTE_REQUIRED_OUTCOMES = [
        self::OUTCOME_WRONG_ADDRESS,
        self::OUTCOME_CUSTOMER_MOVED,
        self::OUTCOME_DISPUTED_BALANCE,
        self::OUTCOME_OTHER,
    ];

    public const GPS_OK = 'ok';

    public const GPS_WARNING = 'warning';

    public const GPS_HIGH_RISK = 'high_risk';

    protected $fillable = [
        'branch_id',
        'customer_id',
        'assignment_id',
        'collector_id',
        'route_stop_id',
        'recorded_by',
        'visited_at',
        'outcome',
        'notes',
        'follow_up_required',
        'follow_up_date',
        'latitude',
        'longitude',
        'gps_accuracy',
        'gps_permission_state',
        'gps_source',
        'gps_captured_at',
        'device_info',
        'ip_address',
        'contact_status',
        'person_met',
        'escalation_flag',
        'origin',
        'signature_placeholder',
        'customer_latitude',
        'customer_longitude',
        'distance_meters',
        'gps_risk_level',
        'correction_note',
        'correction_by',
        'correction_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'gps_accuracy' => 'decimal:2',
            'gps_captured_at' => 'datetime',
            'escalation_flag' => 'boolean',
            'signature_placeholder' => 'boolean',
            'customer_latitude' => 'decimal:7',
            'customer_longitude' => 'decimal:7',
            'distance_meters' => 'decimal:2',
            'correction_at' => 'datetime',
        ];
    }

    public static function outcomeCodes(): array
    {
        return array_keys(self::OUTCOMES);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(CollectionRouteStop::class, 'route_stop_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(UploadedVisitFile::class, 'visit_id');
    }

    public function promise(): HasOne
    {
        return $this->hasOne(PromiseToPay::class, 'visit_id');
    }
}
