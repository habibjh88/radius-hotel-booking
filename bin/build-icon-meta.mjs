#!/usr/bin/env node
/**
 * Generate src/components/icons/lucide-meta.json — the icon picker's
 * categories and search tags — for the installed lucide-react version.
 *
 * Lucide's npm packages carry the icons and their tags, not the categories,
 * so they come from the matching GitHub release:
 *
 *   curl -L https://codeload.github.com/lucide-icons/lucide/tar.gz/refs/tags/<version> | tar -xz
 *   node bin/build-icon-meta.mjs ./lucide-<version>
 *
 * Run it again whenever lucide-react (and lucide-static) are upgraded.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const repo = process.argv[ 2 ];
if ( ! repo || ! fs.existsSync( path.join( repo, 'icons' ) ) ) {
	console.error( 'Usage: node bin/build-icon-meta.mjs <lucide release folder>' );
	process.exit( 1 );
}

const { icons } = require( 'lucide-react' );
// PascalCase export → kebab-case file name, as lucide names them.
const kebab = ( name ) =>
	name
		.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
		.replace( /([A-Z])([A-Z][a-z])/g, '$1-$2' )
		.replace( /([a-zA-Z])([0-9])/g, '$1-$2' )
		.toLowerCase();
const known = new Set(
	fs
		.readdirSync( path.join( repo, 'icons' ) )
		.filter( ( f ) => f.endsWith( '.json' ) )
		.map( ( f ) => f.slice( 0, -5 ) )
);
const pascalOf = {};
for ( const name of Object.keys( icons ) ) {
	const k = kebab( name );
	if ( known.has( k ) ) {
		pascalOf[ k ] = name;
	}
}

const categories = fs
	.readdirSync( path.join( repo, 'categories' ) )
	.filter( ( f ) => f.endsWith( '.json' ) )
	.map( ( f ) => ( {
		key: f.slice( 0, -5 ),
		title: JSON.parse( fs.readFileSync( path.join( repo, 'categories', f ), 'utf8' ) ).title,
		icons: [],
	} ) );
const byKey = Object.fromEntries( categories.map( ( c ) => [ c.key, c ] ) );

const tags = {};
for ( const k of [ ...known ].sort() ) {
	if ( ! pascalOf[ k ] ) {
		continue;
	}
	const meta = JSON.parse( fs.readFileSync( path.join( repo, 'icons', k + '.json' ), 'utf8' ) );
	( meta.categories || [] ).forEach( ( c ) => byKey[ c ]?.icons.push( k ) );
	tags[ k ] = ( meta.tags || [] ).join( ' ' );
}

const out = {
	version: require( 'lucide-react/package.json' ).version,
	categories: categories.filter( ( c ) => c.icons.length ),
	tags,
};
const file = path.resolve( 'src/components/icons/lucide-meta.json' );
fs.writeFileSync( file, JSON.stringify( out ) );
console.log( `${ Object.keys( tags ).length } icons, ${ out.categories.length } categories → ${ file }` );
