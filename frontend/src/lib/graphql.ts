import { GraphQLClient } from 'graphql-request';

export const graphqlClient = new GraphQLClient(process.env.NEXT_PUBLIC_GRAPHQL_URL || 'http://localhost:8000/graphql', {
  fetch: async (url, options) => {
    const headers = new Headers(options?.headers);
    
    // Add X-XSRF-TOKEN for Sanctum CSRF protection on the client side
    if (typeof document !== 'undefined') {
      const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
      if (match && match[2]) {
        headers.set('X-XSRF-TOKEN', decodeURIComponent(match[2]));
      }
    }

    // Add credentials: 'include' so that Sanctum HttpOnly cookies are sent
    const fetchOptions = {
      ...options,
      headers,
      credentials: 'include' as RequestCredentials,
    };
    return fetch(url, fetchOptions);
  },
});
