/**
 * The new-booking sound: the hotel's own file (Settings → Notifications) or
 * the built-in chime, which is synthesised with the Web Audio API, so the
 * plugin ships no audio file.
 *
 * Browsers only play sound after the person has interacted with the page;
 * a blocked play resolves quietly to `false` rather than throwing.
 */

let context = null;

/**
 * Two soft notes, a fifth apart (E6 then B6).
 *
 * @return {Promise<boolean>} Whether it played.
 */
export async function playChime() {
	const AudioContext =
		typeof window !== 'undefined' &&
		( window.AudioContext || window.webkitAudioContext );
	if ( ! AudioContext ) {
		return false;
	}
	try {
		context = context || new AudioContext();
		if ( context.state === 'suspended' ) {
			await context.resume();
		}
		const start = context.currentTime;
		[
			[ 1318.5, 0 ],
			[ 1975.5, 0.16 ],
		].forEach( ( [ frequency, offset ] ) => {
			const oscillator = context.createOscillator();
			const gain = context.createGain();
			oscillator.type = 'sine';
			oscillator.frequency.value = frequency;
			gain.gain.setValueAtTime( 0.0001, start + offset );
			gain.gain.exponentialRampToValueAtTime(
				0.25,
				start + offset + 0.02
			);
			gain.gain.exponentialRampToValueAtTime(
				0.0001,
				start + offset + 0.9
			);
			oscillator.connect( gain ).connect( context.destination );
			oscillator.start( start + offset );
			oscillator.stop( start + offset + 0.95 );
		} );
		return true;
	} catch ( e ) {
		return false;
	}
}

/**
 * Play the notification sound: `url` when given, otherwise the chime.
 *
 * @param {string} url Audio file URL, or '' for the built-in chime.
 * @return {Promise<boolean>} Whether it played.
 */
export async function playNotificationSound( url ) {
	if ( ! url ) {
		return playChime();
	}
	try {
		await new Audio( url ).play();
		return true;
	} catch ( e ) {
		return false;
	}
}
