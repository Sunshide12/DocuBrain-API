<template>
  <v-container class="pa-6">
    <div class="d-flex justify-space-between align-center mb-6">
      <h1 class="text-h4 font-weight-bold text-on-background">Mis Documentos</h1>
      <v-btn variant="outlined" color="secondary" @click="logout">Salir</v-btn>
    </div>
    
    <!-- Upload Zone (Drag & Drop) -->
    <DropZone class="mb-8" @file-dropped="handleUpload" />

    <!-- Document List (Cards) -->
    <v-row>
      <v-col cols="12" sm="6" md="4" v-for="doc in documents" :key="doc.id">
        <v-card class="bg-surface doc-card" rounded="lg" elevation="1">
          <v-card-title class="d-flex justify-space-between align-center">
            <span class="text-truncate mr-2 text-on-surface">{{ doc.title }}</span>
            <v-chip
              :color="statusColor(doc.status)"
              size="small"
              class="font-weight-medium"
            >
              {{ doc.status }}
            </v-chip>
          </v-card-title>
          <v-card-text class="text-on-surface-variant text-caption">
            Subido: {{ new Date(doc.created_at).toLocaleDateString() }}
          </v-card-text>
          <v-card-actions class="pt-0">
            <v-btn variant="text" color="primary" block @click="openChat(doc.id)" :disabled="doc.status !== 'ready'">
              Abrir Chat
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery, useApolloClient } from '@vue/apollo-composable'
import gql from 'graphql-tag'
import DropZone from '@/components/DropZone.vue'
import { uploadDocumentRaw } from '@/plugins/apollo.js'

const router = useRouter()
const { resolveClient } = useApolloClient()

const DOCUMENTS_QUERY = gql`
  query GetDocuments {
    documents(first: 20) {
      data {
        id
        title
        status
        created_at
      }
    }
  }
`

const SUB_MUTATION = gql`
  mutation SubDocumentProgress($id: ID!) {
    subscribeToDocumentProgress: documentProgress(document_id: $id) @client
  }
`

const REGISTER_SUB = `
  subscription DocumentProgress($id: ID!) {
    documentProgress(document_id: $id) {
      status
      progress
    }
  }
`

const documents = ref([])
const { onResult } = useQuery(DOCUMENTS_QUERY)

onResult((res) => {
  if (res.data?.documents?.data) {
    // Apollo congela los objetos del caché — hacemos una copia para poder mutarlos
    documents.value = res.data.documents.data.map(d => ({ ...d }))
  }
})

/**
 * Lanza la suscripción HTTP de Lighthouse para recibir el canal de Reverb
 * y empieza a escuchar los eventos via Echo.
 */
const subscribeToDocument = async (docId) => {
  if (!window.Echo) return
  try {
    const token = localStorage.getItem('token')
    // Registro de suscripción a través del endpoint HTTP de Lighthouse
    const res = await fetch('/graphql', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': token ? `Bearer ${token}` : ''
      },
      body: JSON.stringify({
        query: REGISTER_SUB,
        variables: { id: docId }
      })
    })
    const json = await res.json()
    // Lighthouse devuelve el canal privado de Reverb en las extensiones
    let channel = json.extensions?.lighthouse_subscriptions?.channel
    if (channel) {
      // Echo.private() automáticamente añade "private-", así que si el canal devuelto
      // ya lo incluye (ej: private-lighthouse-...), lo removemos para evitar duplicados (private-private-lighthouse-...)
      if (channel.startsWith('private-')) {
        channel = channel.substring(8)
      }
      
      window.Echo.private(channel).listen('.lighthouse-subscription', (e) => {
        const update = e.result?.data?.documentProgress || e.data?.documentProgress
        if (!update) return
        const docIndex = documents.value.findIndex(d => d.id == docId)
        if (docIndex !== -1) {
          documents.value[docIndex].status = update.status
        }
      })
    }
  } catch (err) {
    console.error('Error suscribiendo al documento:', err)
  }
}

const handleUpload = async (file) => {
  try {
    const newDoc = await uploadDocumentRaw(file)
    documents.value.unshift(newDoc)
    subscribeToDocument(newDoc.id)
  } catch (error) {
    alert('Error al subir: ' + error.message)
  }
}

const openChat = (id) => {
  router.push({ name: 'DocumentChat', params: { id } })
}

const statusColor = (status) => {
  if (status === 'ready' || status === 'completed') return 'success'
  if (status === 'processing') return 'warning'
  if (status === 'failed' || status === 'error') return 'error'
  return 'secondary'
}

const logout = () => {
  localStorage.removeItem('token')
  router.push({ name: 'Auth' })
}
</script>

<style scoped>
.doc-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.doc-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.2) !important;
}
</style>
