import { resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = fileURLToPath( new URL( '.', import.meta.url ) );
const root = resolve( __dirname, '.' );

/** @type {import('vite').UserConfig} */
export default {
	root,
	publicDir: resolve( root, 'public' ),
	build: {
		outDir: 'build',
		emptyOutDir: true,
		rollupOptions: {
			input: {
				main: 'src/main.js',
			},
			output: {
				entryFileNames: '[name].js',
				chunkFileNames: '[name]-[hash].js',
				assetFileNames: '[name][extname]',
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
