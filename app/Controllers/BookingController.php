<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\RbacService;

class BookingController
{
    private BookingService $bookings;
    private RbacService $rbac;

    public function __construct()
    {
        $this->bookings = new BookingService();
        $this->rbac = new RbacService();
    }

    public function store(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'bookings.create', 'You do not have permission to create bookings');

        $data = $request->only(['booking_date', 'slot_id', 'centre_id', 'crops']);
        $booking = $this->bookings->create($actor, $data);

        Response::created([
            'booking' => $booking,
            'message' => 'Booking created',
        ]);
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'bookings.view_own', 'You do not have permission to view bookings');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $result = $this->bookings->list(
            $actor,
            $request->only(['status', 'date_from', 'date_to', 'from', 'to', 'q']),
            $page,
            $perPage
        );

        Response::success($result['data'], ['pagination' => $result['pagination']]);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'bookings.view_own', 'You do not have permission to view bookings');

        $booking = $this->bookings->show($actor, (int) $request->getParam('id'));

        Response::success(['booking' => $booking]);
    }

    public function crops(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'bookings.view_own', 'You do not have permission to view bookings');

        $booking = $this->bookingOr404((int) $request->getParam('id'));
        $crops = $this->bookings->cropsFor($actor, $booking);

        Response::success(['crops' => $crops]);
    }

    public function cancel(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'bookings.view_own', 'You do not have permission to cancel bookings');

        $booking = $this->bookingOr404((int) $request->getParam('id'));
        $updated = $this->bookings->cancel($actor, $booking, $request->input('reason', null));

        Response::success([
            'booking' => $updated,
            'message' => 'Booking cancelled',
        ]);
    }

    public function myToken(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'tokens.view_own', 'You do not have permission to view your token');

        $token = $this->bookings->myToken($actor);

        Response::success([
            'token' => $token,
        ]);
    }

    private function bookingOr404(int $bookingId): array
    {
        $booking = (new Booking())->find($bookingId);
        if ($booking === null) {
            throw new NotFoundException('BOOKING_NOT_FOUND', 'Booking not found');
        }
        return $booking;
    }

    private function requireActor(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }
        return $user;
    }
}
