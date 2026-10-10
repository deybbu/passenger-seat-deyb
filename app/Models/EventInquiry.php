<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventInquiry extends Model
{
    public const STATUS_PENDING = 'pending';

    public const BLOCKING_STATUSES = ['pending', 'approved', 'confirmed', 'booked'];

    public const BOOKED_STATUSES = ['approved', 'confirmed', 'booked'];

    public const EVENT_TYPES = [
        'Birthday',
        'Wedding reception',
        'Corporate event',
        'Christening / Baptism',
        'Reunion',
        'Whole-restaurant rental',
        'Other',
    ];

    public const PREFERRED_TIMES = ['Lunch', 'Afternoon', 'Dinner', 'Evening'];

    public const PACKAGE_OPTIONS = [
        'Boodle fight package',
        'Set menu',
        'Whole-restaurant rental',
        'Custom, discuss with manager',
    ];

    protected $fillable = [
        'reference',
        'event_date',
        'first_name',
        'last_name',
        'email',
        'mobile_number',
        'event_type',
        'preferred_time',
        'guest_count',
        'preferred_package',
        'dietary_restrictions',
        'special_requests',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'guest_count' => 'integer',
        ];
    }
}
