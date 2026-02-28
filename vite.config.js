import { defineConfig } from 'vite';

export default defineConfig( {
	build: {
		outDir: 'build',
		emptyOutDir: true,
		minify: true,
		sourcemap: false,
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
	},
} );
