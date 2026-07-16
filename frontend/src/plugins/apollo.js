import { ApolloClient, createHttpLink, InMemoryCache } from '@apollo/client/core'
import { setContext } from '@apollo/client/link/context'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: 'docubrain-key',
  wsHost: window.location.hostname,
  wsPort: 8080,
  wssPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: '/graphql/subscriptions/auth',
  authorizer: (channel, options) => {
    return {
      authorize: (socketId, callback) => {
        const token = localStorage.getItem('token')
        fetch(options.authEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': token ? `Bearer ${token}` : ''
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name
          })
        })
        .then(response => response.json())
        .then(data => callback(false, data))
        .catch(error => callback(true, error))
      }
    }
  }
})

const httpLink = createHttpLink({
  uri: '/graphql',
})

const authLink = setContext((_, { headers }) => {
  const token = localStorage.getItem('token')
  return {
    headers: {
      ...headers,
      authorization: token ? `Bearer ${token}` : "",
    }
  }
})

export const apolloClient = new ApolloClient({
  link: authLink.concat(httpLink),
  cache: new InMemoryCache()
})

/**
 * Sube un archivo PDF usando la GraphQL Multipart Request Spec
 * (https://github.com/jaydenseric/graphql-multipart-request-spec)
 * sin ninguna dependencia adicional.
 */
export async function uploadDocumentRaw(file) {
  const token = localStorage.getItem('token')
  const query = `
    mutation UploadDocument($file: Upload!) {
      uploadDocument(file: $file) {
        id
        title
        status
        created_at
      }
    }
  `

  // Construir el FormData siguiendo la spec multipart
  const formData = new FormData()
  formData.append('operations', JSON.stringify({
    query,
    variables: { file: null }
  }))
  formData.append('map', JSON.stringify({ '0': ['variables.file'] }))
  formData.append('0', file, file.name)

  const response = await fetch('/graphql', {
    method: 'POST',
    headers: {
      'Authorization': token ? `Bearer ${token}` : '',
      // No ponemos Content-Type: el browser lo pone automáticamente con el boundary
    },
    body: formData
  })

  const json = await response.json()

  if (json.errors) {
    throw new Error(json.errors.map(e => e.message).join('\n'))
  }

  return json.data.uploadDocument
}
