/**
 * Settings screen state: what is saved, what is being edited, and the field
 * errors the server returned, per section.
 *
 * Each section keeps its own draft, so switching tabs never loses edits; a
 * section is "dirty" while its draft differs from what was saved. Saving sends
 * the draft with `PUT settings/<section>`; a 422 puts the server's messages on
 * the fields (`errors[ section ][ key ]`) and keeps the draft.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { get, put } from '@/api/client';
import { toast, toastError } from '@/lib/toast';

/**
 * Stable comparison of two plain values.
 *
 * @param {*} a Value.
 * @param {*} b Value.
 * @return {boolean} Equal.
 */
const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

/**
 * Server field errors → key => first message.
 *
 * @param {Object} errors `error.errors` from the API client.
 * @return {Object} Messages.
 */
const messagesOf = ( errors ) =>
	Object.fromEntries(
		Object.entries( errors || {} ).map( ( [ key, detail ] ) => [
			key,
			detail?.first_message ||
				( Array.isArray( detail?.messages )
					? detail.messages[ 0 ]
					: '' ) ||
				( typeof detail === 'string' ? detail : '' ),
		] )
	);

/**
 * @return {Object} State and actions.
 */
export default function useSettingsDrafts() {
	const [ saved, setSaved ] = useState( null );
	const [ drafts, setDrafts ] = useState( null );
	const [ schema, setSchema ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ loadError, setLoadError ] = useState( null );
	const [ saving, setSaving ] = useState( '' );

	const load = useCallback( () => {
		setLoadError( null );
		Promise.all( [ get( 'settings' ), get( 'settings/schema' ) ] )
			.then( ( [ settings, schemaResponse ] ) => {
				const values = settings.data.settings ?? settings.data;
				setSaved( values );
				setDrafts( values );
				setSchema( schemaResponse.data.schema ?? {} );
			} )
			.catch( setLoadError );
	}, [] );

	useEffect( load, [ load ] );

	const setField = useCallback(
		( section ) => ( key ) => ( value ) => {
			setDrafts( ( current ) => ( {
				...current,
				[ section ]: {
					...( current?.[ section ] || {} ),
					[ key ]: value,
				},
			} ) );
			// Editing a field clears its error.
			setErrors( ( current ) =>
				current[ section ]?.[ key ]
					? {
							...current,
							[ section ]: { ...current[ section ], [ key ]: '' },
					  }
					: current
			);
		},
		[]
	);

	const isDirty = useCallback(
		( section ) =>
			!! saved &&
			!! drafts &&
			! same( saved[ section ], drafts[ section ] ),
		[ saved, drafts ]
	);

	const dirtySections = useMemo(
		() =>
			saved && drafts
				? Object.keys( drafts ).filter(
						( key ) => ! same( saved[ key ], drafts[ key ] )
				  )
				: [],
		[ saved, drafts ]
	);

	/**
	 * Store what the server returned as both saved and draft.
	 *
	 * @param {string} section Section key.
	 * @param {Object} data    Section values.
	 */
	const accept = ( section, data ) => {
		setSaved( ( current ) => ( { ...current, [ section ]: data } ) );
		setDrafts( ( current ) => ( { ...current, [ section ]: data } ) );
		setErrors( ( current ) => ( { ...current, [ section ]: {} } ) );
	};

	const save = useCallback(
		async ( section ) => {
			setSaving( section );
			try {
				const { data } = await put(
					`settings/${ section }`,
					drafts[ section ]
				);
				accept( section, data.data ?? drafts[ section ] );
				toast.success(
					__( 'Settings saved.', 'radius-hotel-booking' )
				);
				return data.data ?? drafts[ section ];
			} catch ( error ) {
				if ( error?.errors && Object.keys( error.errors ).length ) {
					setErrors( ( current ) => ( {
						...current,
						[ section ]: messagesOf( error.errors ),
					} ) );
				}
				toastError( error );
				return null;
			} finally {
				setSaving( '' );
			}
		},
		[ drafts ]
	);

	const discard = useCallback(
		( section ) => {
			setDrafts( ( current ) => ( {
				...current,
				[ section ]: saved[ section ],
			} ) );
			setErrors( ( current ) => ( { ...current, [ section ]: {} } ) );
		},
		[ saved ]
	);

	const reset = useCallback( async ( section ) => {
		setSaving( section );
		try {
			const { data } = await put( `settings/${ section }/reset` );
			accept( section, data.data );
			toast.success( __( 'Defaults restored.', 'radius-hotel-booking' ) );
			return data.data;
		} catch ( error ) {
			toastError( error );
			return null;
		} finally {
			setSaving( '' );
		}
	}, [] );

	return {
		saved,
		drafts,
		schema,
		errors,
		loadError,
		reload: load,
		saving,
		setField,
		isDirty,
		dirtySections,
		save,
		discard,
		reset,
	};
}
