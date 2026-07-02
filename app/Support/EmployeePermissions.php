<?php

namespace App\Support;

final class EmployeePermissions
{
    public const CREATE_RESERVATIONS = 'create_reservations';

    public const EDIT_RESERVATIONS = 'edit_reservations';

    public const CANCEL_RESERVATIONS = 'cancel_reservations';

    public const VIEW_CUSTOMERS = 'view_customers';

    public const ADD_CUSTOMER_NOTES = 'add_customer_notes';

    public const VIEW_CALENDAR = 'view_calendar';

    public const VIEW_ASSIGNED_FIELDS = 'view_assigned_fields';

    public static function all(): array
    {
        return [
            self::CREATE_RESERVATIONS,
            self::EDIT_RESERVATIONS,
            self::CANCEL_RESERVATIONS,
            self::VIEW_CUSTOMERS,
            self::ADD_CUSTOMER_NOTES,
            self::VIEW_CALENDAR,
            self::VIEW_ASSIGNED_FIELDS,
        ];
    }
}
