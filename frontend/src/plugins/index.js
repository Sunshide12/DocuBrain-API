import router from '../router';
import {createPinia} from 'pinia';
/**
 * plugins/index.ts
 *
 * Automatically included in `./src/main.ts`
 */

// Types

// Plugins
import vuetify from './vuetify'
import { DefaultApolloClient } from '@vue/apollo-composable'
import { apolloClient } from './apollo'

export function registerPlugins (app) {
 app.use(vuetify)
 app.use(createPinia());
 app.use(router);
 app.provide(DefaultApolloClient, apolloClient);
}