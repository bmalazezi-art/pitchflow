# Future Google Calendar Integration

Google Calendar synchronization is intentionally not implemented yet. Reservations remain internal to PitchFlow.

## Extension point

When integration work begins, publish a reservation lifecycle event after the existing reservation database transaction commits. A queued, provider-specific listener can then create, update, or remove the employee's external calendar event without coupling Google APIs to `ReservationService`.

Store provider credentials encrypted and per user. Store the external calendar ID and event ID in a separate integration table keyed by organization, employee, and reservation. Never place OAuth tokens on reservation or user records.

## Privacy boundary

Only the assigned employee may authorize their calendar. Sync the minimum event data required for operations. Customer notes, reliability indicators, phone history, and other private customer metadata must never be included. Public availability remains limited to slot status.

## Failure handling

Provider calls must run in a queue with retries and idempotency. A sync failure must not roll back or block an internal reservation. Record integration failures in internal activity logs without storing access tokens or customer details in log messages.
