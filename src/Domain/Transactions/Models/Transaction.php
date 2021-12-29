<?php
namespace VueFileManager\Subscription\Domain\Transactions\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use VueFileManager\Subscription\Database\Factories\TransactionFactory;
use VueFileManager\Subscription\Domain\FailedPayments\Models\FailedPayment;

/**
 * @method static create(array $array)
 * @property string id
 * @property string user_id
 * @property string note
 * @property string status
 * @property string currency
 * @property int amount
 * @property string driver
 * @property string reference
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Transaction extends Model
{
    use HasFactory;
    use Sortable;

    protected $guarded = [];

    protected $casts = [
        'id' => 'string',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    public function user(): HasOne
    {
        return $this->hasOne(config('auth.providers.users.model'), 'id', 'user_id');
    }

    public function failedPayment(): HasOne
    {
        return $this->hasOne(FailedPayment::class, 'transaction_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            $plan->id = Str::uuid();
        });
    }
}
