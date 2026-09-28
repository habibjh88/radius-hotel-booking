/**
 * Item REST calls.
 *
 * One file per module keeps every endpoint a screen touches in one place.
 */
import { del, get, post, put } from '@/api/client';

export const fetchItems = ( query ) => get( 'items', query );

export const fetchItem = ( id ) => get( `items/${ id }` );

export const createItem = ( data ) => post( 'items', data );

export const updateItem = ( id, data ) => put( `items/${ id }`, data );

export const deleteItem = ( id ) => del( `items/${ id }` );

export const publishItem = ( id ) => put( `items/${ id }/publish` );
