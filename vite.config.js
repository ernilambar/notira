import { svelte } from '@sveltejs/vite-plugin-svelte';
import { resolve } from 'path';
import { fileURLToPath } from 'url';
import browserslistToEsbuild from 'browserslist-to-esbuild';

const __dirname = fileURLToPath( new URL( '.', import.meta.url ) );
const root = resolve( __dirname, '.' );

/** @type {import('vite').UserConfig} */
export default {
	root,
	plugins: [ svelte() ],
	publicDir: resolve( root, 'public' ),
	build: {
		outDir: 'build',
		emptyOutDir: true,
		sourcemap: false,
		target: browserslistToEsbuild(),
		rollupOptions: {
			input: {
				main: 'src/main.js',
				settings: 'src/settings.js',
			},
			output: {
				entryFileNames: '[name].js',
				chunkFileNames: '[name]-[hash].js',
				assetFileNames: '[name][extname]',
			},
		},
	},
	resolve: {
		alias: {
			'~': root,
		},
	},
};
