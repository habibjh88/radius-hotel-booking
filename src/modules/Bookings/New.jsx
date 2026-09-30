/**
 * New booking at the front desk (`#/bookings/new`, M02): the shared booking
 * flow in staff mode.
 */
import BookingFlow from '@/components/booking/BookingFlow';

/**
 * @return {JSX.Element} Screen.
 */
export default function NewBooking() {
	return (
		<div className="mx-auto max-w-6xl">
			<BookingFlow mode="desk" />
		</div>
	);
}
