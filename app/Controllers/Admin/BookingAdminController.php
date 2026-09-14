<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\RbacService;

class BookingAdminController
{
    private BookingService $bookings;
    private RbacService $rbac;

    public function __construct()
    {
        $this->bookings = new BookingService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $result = $this->bookings->adminList(
            $actor,
            $request->only(['centre_id', 'status', 'date', 'date_from', 'date_to', 'from', 'to', 'q']),
            $page,
            $perPage
        );

        Response::success($result['data'], ['pagination' => $result['pagination']]);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);

        $booking = $this->bookings->show($actor, (int) $request->getParam('id'));

        Response::success(['booking' => $booking]);
    }

    public function cancel(Request $request): void
    {
        $actor = $this->requireActor($request);

        $booking = $this->bookingOr404((int) $request->getParam('id'));
        $updated = $this->bookings->adminCancel($actor, $booking, $request->only(['reason']));

        Response::success([
            'booking' => $updated,
            'message' => 'Booking cancelled',
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
