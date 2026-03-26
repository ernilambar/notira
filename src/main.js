import './css/main.css';
import './App.css';

import { mount } from 'svelte';

import App from './App.svelte';

const root = document.getElementById( 'notira-root' );
if ( root ) {
	mount( App, { target: root } );
}
