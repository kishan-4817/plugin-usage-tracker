import { copyFile, mkdir, readdir, rm, stat } from 'node:fs/promises';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const buildDir = join( rootDir, 'build', 'plugin-usage-tracker' );
const runtimeItems = [
	'plugin-usage-tracker.php',
	'uninstall.php',
	'README.md',
	'assets',
	'includes',
	'languages',
	'vendor',
];

function shouldSkip( relativePath ) {
	const normalized = relativePath.replaceAll( '\\', '/' ).toLowerCase();

	return (
		normalized === '.git' ||
		normalized.startsWith( '.git/' ) ||
		normalized.includes( '/.git/' ) ||
		normalized.endsWith( '/.git' ) ||
		normalized.includes( '/tests/' ) ||
		normalized.startsWith( 'tests/' ) ||
		normalized.includes( '/test/' ) ||
		normalized.startsWith( 'test/' ) ||
		normalized.endsWith( '.php3' )
	);
}

async function copyRecursive( currentSource, currentDestination, relativeRoot ) {
	const entries = await readdir( currentSource, { withFileTypes: true } );

	await mkdir( currentDestination, { recursive: true } );

	for ( const entry of entries ) {
		const sourcePath = join( currentSource, entry.name );
		const destinationPath = join( currentDestination, entry.name );
		const relativePath = relative( relativeRoot, sourcePath );

		if ( shouldSkip( relativePath ) ) {
			continue;
		}

		if ( entry.isDirectory() ) {
			await copyRecursive( sourcePath, destinationPath, relativeRoot );
			continue;
		}

		if ( entry.isFile() ) {
			await mkdir( dirname( destinationPath ), { recursive: true } );
			await copyFile( sourcePath, destinationPath );
		}
	}
}

async function copyIfPresent( item ) {
	const source = join( rootDir, item );
	const destination = join( buildDir, item );

	try {
		const sourceStats = await stat( source );

		if ( sourceStats.isFile() ) {
			await mkdir( dirname( destination ), { recursive: true } );
			await copyFile( source, destination );
			return;
		}

		if ( sourceStats.isDirectory() ) {
			await copyRecursive( source, destination, source );
		}
	} catch ( error ) {
		if ( error.code !== 'ENOENT' ) {
			throw error;
		}
	}
}

await rm( join( rootDir, 'build' ), { recursive: true, force: true } );
await mkdir( buildDir, { recursive: true } );

for ( const item of runtimeItems ) {
	await copyIfPresent( item );
}

console.log( `Built plugin package in ${ buildDir }` );
