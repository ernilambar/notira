import { build } from 'vite';
import { resolve } from 'path';
import { fileURLToPath } from 'url';
import { readFileSync } from 'fs';

const __dirname = fileURLToPath( new URL( '.', import.meta.url ) );
const root = resolve( __dirname, '..' );

const pkg = JSON.parse( readFileSync( resolve( root, 'package.json' ), 'utf8' ) );
const buildConfig = pkg.build || {};
const entries = buildConfig.entries || [];
const srcDir = buildConfig.srcDir || 'src';
const outDir = buildConfig.outDir || 'build';

/**
 * Returns Vite config for one entry. Used for per-entry builds so each output
 * is self-contained (one JS + one CSS) with predictable filenames for the manifest.
 */
function getConfig( entryName, isFirstEntry, watch = false ) {
	return {
		root,
		configFile: false,
		publicDir: resolve( root, 'public' ),
		build: {
			emptyOutDir: isFirstEntry,
			outDir,
			watch: watch ? {} : undefined,
			lib: {
				entry: resolve( root, srcDir, `${ entryName }.js` ),
				name: entryName.replace( /-/g, '_' ),
				fileName: () => `${ entryName }.js`,
				formats: [ 'iife' ],
			},
			rollupOptions: {
				output: {
					entryFileNames: `${ entryName }.js`,
					assetFileNames: ( assetInfo ) => {
						const name = assetInfo.name || '';
						return name.endsWith( '.css' )
							? `${ entryName }.css`
							: 'assets/[name]-[hash][extname]';
					},
				},
			},
			sourcemap: false,
		},
		resolve: {
			alias: {
				'~': root,
			},
		},
	};
}

const watch = process.argv.includes( '--watch' );

if ( watch ) {
	await Promise.all( entries.map( ( name, i ) => build( getConfig( name, i === 0, true ) ) ) );
} else {
	for ( let i = 0; i < entries.length; i++ ) {
		await build( getConfig( entries[ i ], i === 0, false ) );
	}
}
