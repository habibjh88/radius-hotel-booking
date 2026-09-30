/**
 * Notes on a record (M09; reused by M03 and M12): REST calls and React Query
 * hooks for `NotesPanel`.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { del, get, post, put } from '@/api/client';

/**
 * The query key of a record's notes.
 *
 * @param {string} type Notable type, e.g. `guest`.
 * @param {number} id   Record id.
 * @return {Array} Key.
 */
export const notesKey = ( type, id ) => [ 'notes', type, Number( id ) ];

/**
 * A record's notes, newest first, and whether the viewer may add one.
 *
 * @param {string} type Notable type.
 * @param {number} id   Record id.
 * @return {Object} React Query result; data `{ notes, canAdd }`.
 */
export function useNotes( type, id ) {
	return useQuery( {
		queryKey: notesKey( type, id ),
		queryFn: () =>
			get( 'notes', { type, id } ).then( ( { data } ) => ( {
				notes: data.notes,
				canAdd: Boolean( data.can_add ),
			} ) ),
		enabled: Boolean( type && id ),
	} );
}

/**
 * Add, edit or remove a note of a record; refreshes its notes (and its
 * history, which logs every change).
 *
 * @param {string} type Notable type.
 * @param {number} id   Record id.
 * @return {Object} `{ add, edit, remove }` mutations.
 */
export function useNoteMutations( type, id ) {
	const client = useQueryClient();
	const onSuccess = () => {
		client.invalidateQueries( { queryKey: notesKey( type, id ) } );
		client.invalidateQueries( { queryKey: [ 'activity' ] } );
	};
	return {
		add: useMutation( {
			mutationFn: ( fields ) =>
				post( 'notes', {
					notable_type: type,
					notable_id: id,
					...fields,
				} ).then( ( { data } ) => data.note ),
			onSuccess,
		} ),
		edit: useMutation( {
			mutationFn: ( { noteId, ...fields } ) =>
				put( `notes/${ noteId }`, fields ).then(
					( { data } ) => data.note
				),
			onSuccess,
		} ),
		remove: useMutation( {
			mutationFn: ( noteId ) => del( `notes/${ noteId }` ),
			onSuccess,
		} ),
	};
}
