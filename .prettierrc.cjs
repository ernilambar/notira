const wpConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpConfig,
	printWidth: 100,
	plugins: [ 'prettier-plugin-svelte' ],
	overrides: [
		...wpConfig.overrides,
		{
			files: '*.svelte',
			options: {
				parser: 'svelte',
			},
		},
	],
};
