# Taxi and Delivery Module Plan

## Product intent

Add a transport marketplace for South African local work: passenger trips, parcels, errands, and larger deliveries. The system must support anyone with safe, approved transport: bicycle, scooter, motorbike, car, bakkie, LDV, van, trailer, or larger local delivery vehicle.

The workflow should feel close to an Uber-style dispatch flow:

- Clients create a transport request.
- Available on-duty drivers see matching incoming requests in realtime.
- A driver accepts one request.
- The client sees driver details, live approach/location updates, status changes, and payment state.
- The driver and client both have panic/safety tools.
- The platform records the transaction and keeps 10% of the vehicle fee.

## Roles and access

Add these role slugs:

- `transport_manager`: created by Dev/admin in the backend. Can manage drivers, vehicles, pricing, service areas, documents, safety settings, and request oversight.
- `transport_driver`: can clock in/out, manage current duty status, see incoming requests only while available/on duty, accept jobs, update progress, collect payment where allowed, and use panic tools.
- `registered_user`: can request rides/deliveries and track assigned drivers.
- `super_admin`: can see and override all transport operations.
- `support`: can monitor live trips, handle panic events, and intervene without changing pricing unless granted.

Visibility rule:

- The driver transport page should only appear when a signed-in `transport_driver` has an active duty session with status `available` or `busy`.
- Incoming request cards should only show while the driver is `available`, approved, and operating an approved vehicle matching the request requirements.
- Driver manager pages should be visible to `transport_manager` and `super_admin`.

## Core workflows

### Manager onboarding

1. Dev/admin creates a user with the `transport_manager` role.
2. Manager opens `/transport/manager`.
3. Manager creates driver profiles, links them to user accounts, uploads or records verification documents, adds emergency contacts, and assigns approved vehicles.
4. Manager configures vehicle categories, rates, service areas, payment options, commission rules, and whether a vehicle can carry passengers, parcels, or both.
5. Driver can only clock in when the profile and vehicle are approved.

### Driver duty flow

1. Driver signs in.
2. Driver selects an approved vehicle.
3. Driver clocks in as available.
4. System creates a duty session and begins accepting location heartbeats.
5. Driver sees matching incoming requests in realtime.
6. Driver accepts a request.
7. System atomically assigns that request, marks driver busy, and broadcasts updates to client, manager, and nearby drivers.
8. Driver updates statuses: accepted, arriving, arrived pickup, loaded/onboarded, in transit, arrived dropoff, completed.
9. Driver clocks out when finished.

### Client request flow

1. Client chooses ride, parcel, shopping/errand, or larger delivery.
2. Client supplies pickup/dropoff, package/passenger details, vehicle requirements, notes, and payment choice.
3. System estimates price based on distance, service type, capacity, pricing model, and optional passenger count.
4. Client confirms request.
5. Nearby eligible drivers receive the request.
6. Client sees accepted driver details and live map movement.
7. Client confirms completion or system completes after driver completion plus optional dispute window.

## Vehicle model

Each vehicle should have:

- Owner/driver link.
- Transport type: bicycle, scooter, motorcycle, car, bakkie, LDV, van, truck, trailer.
- Capacity: max passengers, max parcel weight, max dimensions, refrigerated/fragile support if needed later.
- Service modes: passengers, parcels, grocery/errand, heavy goods.
- Pricing mode: `per_km` or `per_km_plus_people`.
- Base fee, per-km fee, per-person fee, minimum fee, waiting fee, cancellation fee.
- Payment capabilities: PayFast online, cash accepted, driver card machine available.
- Approval status and document expiry tracking.

For small job creation, bicycle and walking-distance delivery should not be treated as second-class transport. They should be first-class vehicle categories with small parcel size limits and lower minimum fees.

## Payments and transaction management

Payment methods:

- PayFast online payment.
- Cash to driver.
- Card machine carried by driver, if the vehicle/driver is configured for it.

Ledger behavior:

- Every completed trip/delivery creates a transport transaction.
- Gross vehicle fee is the amount charged to client.
- Platform commission is 10% of the vehicle fee.
- Driver earning is 90% minus any explicit penalties/refunds.
- Online PayFast payments should flow through the existing order/payment infrastructure where possible, then create transport ledger entries.
- Cash/card-machine payments must still be recorded in-app and reconciled by manager/admin.

Important statuses:

- `quoted`, `payment_pending`, `dispatching`, `accepted`, `driver_arriving`, `pickup_arrived`, `in_transit`, `dropoff_arrived`, `completed`, `cancelled`, `disputed`, `refunded`.

Suggested financial tables:

- `transport_transactions`
- `transport_ledger_entries`
- `transport_payouts` or reuse existing payout flow after checking fit
- `transport_payment_reconciliations`

## Realtime and websockets

Use websocket channels for:

- Incoming request dispatch to eligible available drivers.
- Driver acceptance and request status changes.
- Driver location streaming to the assigned client and manager console.
- Client cancellation or note changes.
- Panic events and safety alerts.

Recommended Laravel approach:

- Add Laravel broadcasting with a websocket server such as Laravel Reverb, or use a hosted Pusher-compatible websocket provider if deployment constraints make that simpler.
- Frontend consumes realtime events with Laravel Echo.
- Private channels should be used for driver, client, manager, and request-specific updates.

Channels:

- `private-transport.driver.{driverId}`
- `private-transport.client.{userId}`
- `private-transport.request.{requestId}`
- `private-transport.manager.{managerId}`
- `private-transport.safety`

Fallback:

- If websocket connection drops, clients should poll current request status every few seconds and drivers should keep a visible reconnect state.

## Safety and panic features

South African safety needs to be central, not an afterthought.

Driver safety:

- Panic button on active request page.
- Configurable emergency contacts.
- Option to call/SMS manager, emergency contact, or a configured response number.
- Sends current GPS location, request ID, client details, vehicle, and timestamp.
- Creates a `transport_safety_events` record.
- Broadcasts to manager/support safety console.

Client safety:

- Panic button on tracking page.
- Share trip link/status with trusted contact.
- Show driver name, vehicle, registration, rating/status, and manager contact.
- Safety event should send current client location if available, assigned driver details, request ID, and timestamp.

Operational safety:

- Driver approval workflow with documents.
- Vehicle approval workflow.
- Audit logs for manager changes.
- Optional masked phone contact later.
- Trip code/PIN at pickup for passengers or sensitive parcels.
- Photo proof of pickup/dropoff for parcels.
- Dispute flow and incident notes.

Suggested safety tables:

- `transport_emergency_contacts`
- `transport_safety_events`
- `transport_trip_shares`
- `transport_proofs`

## Suggested database entities

- `transport_drivers`
- `transport_driver_documents`
- `transport_vehicles`
- `transport_vehicle_documents`
- `transport_driver_vehicle`
- `transport_duty_sessions`
- `transport_location_updates`
- `transport_requests`
- `transport_request_offers`
- `transport_request_status_events`
- `transport_pricing_profiles`
- `transport_transactions`
- `transport_ledger_entries`
- `transport_safety_events`
- `transport_emergency_contacts`
- `transport_service_areas`

Key implementation notes:

- Accepting a request must be transactional and protected against two drivers accepting the same request.
- Store recent location updates efficiently. Keep full route history only when needed for safety, disputes, or analytics.
- Do not expose all driver locations publicly. Only assigned client and manager/support should see the live driver for an active request.
- Keep panic events immutable except for admin resolution notes.

## Pages

Public/client:

- `/transport` request start page.
- `/transport/requests/create`
- `/transport/requests/{request}` tracking page.
- `/transport/requests/{request}/pay`

Driver:

- `/transport/driver` duty dashboard.
- `/transport/driver/requests/{request}` active job page.
- `/transport/driver/earnings`
- `/transport/driver/safety`

Manager:

- `/transport/manager`
- `/transport/manager/drivers`
- `/transport/manager/vehicles`
- `/transport/manager/pricing`
- `/transport/manager/requests`
- `/transport/manager/transactions`
- `/transport/manager/safety`

Admin:

- Add transport panels into existing admin finance, audit, settings, and user management where appropriate.

## Build phases

### Phase 1: Foundation

- Add roles and permissions.
- Add core transport tables and models.
- Add manager CRUD for drivers, vehicles, pricing, and emergency contacts.
- Add driver clock-in/out with vehicle selection.
- Gate driver page visibility by active duty session.
- Add focused feature tests for role visibility and duty gating.

### Phase 2: Request and assignment workflow

- Add client request creation.
- Add fare estimate service.
- Add request offer matching by driver availability, vehicle type, capacity, and service mode.
- Add atomic accept endpoint.
- Add status timeline.
- Add driver and client request pages.

### Phase 3: Realtime

- Install/configure websocket broadcasting.
- Add events for incoming request, accepted request, status changes, location updates, and panic events.
- Add Laravel Echo frontend listeners.
- Add reconnect and polling fallback.

### Phase 4: Payments and commission

- Integrate PayFast checkout for transport requests.
- Add cash and driver card-machine recording.
- Add transaction ledger with 10% platform commission.
- Add manager reconciliation screens.
- Add payout/reporting support.

### Phase 5: Safety and trust

- Add panic buttons.
- Add emergency contact configuration.
- Add manager/support safety console.
- Add trip sharing and pickup/dropoff PIN or proof of delivery.
- Add incident notes and dispute handling.

### Phase 6: Operational hardening

- Add service area controls.
- Add document expiry warnings.
- Add fraud/abuse limits.
- Add performance indexes.
- Add production websocket deployment checks.
- Add reporting for drivers, managers, and platform commission.

## First implementation recommendation

Start with Phase 1 and Phase 2 before adding the full realtime layer. Build the database and lifecycle cleanly first, then wire websocket events onto stable state transitions. That keeps safety, payments, and driver assignment reliable instead of making the live map the source of truth.

