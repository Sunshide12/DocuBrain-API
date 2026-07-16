<template>
  <v-container fluid class="pa-0 fill-height bg-background docu-chat-container">
    <v-row no-gutters class="fill-height">
      
      <!-- MAIN AREA: Conversación Activa -->
      <v-col
        cols="12"
        class="d-flex flex-column bg-background fill-height"
      >
        <!-- Chat Header -->
        <v-toolbar color="surface" elevation="0" class="border-b" border-color="surface-light">
          <v-btn icon class="mr-2" @click="goBack">
            <v-icon>mdi-chevron-left</v-icon>
          </v-btn>
          <v-toolbar-title class="font-weight-bold text-on-surface text-body-1">
            Chat: {{ docId }}
          </v-toolbar-title>
        </v-toolbar>

        <!-- Mensajes (Burbujas) -->
        <div class="flex-grow-1 overflow-y-auto pa-4 pa-md-6 chat-messages-area">
          <div
            v-for="(msg, i) in messages"
            :key="i"
            class="d-flex mb-6"
            :class="msg.isMine ? 'justify-end' : 'justify-start'"
          >
            <!-- Avatar IA -->
            <v-avatar v-if="!msg.isMine" size="32" class="mr-2 mt-auto align-self-end bg-primary">
               <v-icon color="white" size="small">mdi-robot-outline</v-icon>
            </v-avatar>

            <!-- Burbuja de mensaje -->
            <div
              class="message-bubble pa-3 pa-md-4 text-body-2"
              :class="msg.isMine ? 'bg-primary text-on-primary rounded-bubble-mine' : 'bg-surface-light text-on-surface rounded-bubble-other'"
            >
              {{ msg.text }}
              
              <!-- Componente de citas/chunks si es la IA -->
              <div v-if="msg.chunks" class="mt-2 pt-2 border-t border-opacity-20 d-flex flex-wrap gap-2">
                 <v-chip v-for="chunk in msg.chunks" :key="chunk.id" size="x-small" variant="tonal" color="background">
                   Ref: {{ chunk.id }}
                 </v-chip>
              </div>
            </div>
          </div>
        </div>

        <!-- Input Area (Bottom) -->
        <v-sheet color="background" class="pa-4 border-t border-color-surface-light">
          <v-text-field
            v-model="newMessage"
            placeholder="Type a message..."
            variant="solo-filled"
            bg-color="surface"
            hide-details
            rounded="pill"
            @keyup.enter="sendMessage"
          >
            <template v-slot:append-inner>
              <v-btn icon variant="text" color="primary" @click="sendMessage">
                <v-icon>mdi-send</v-icon>
              </v-btn>
            </template>
          </v-text-field>
        </v-sheet>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useMutation } from '@vue/apollo-composable'
import gql from 'graphql-tag'

const router = useRouter()
const route = useRoute()
const docId = ref(route.params.id)
const conversationId = ref(null)

const messages = ref([
  { text: 'Hola, soy la IA analizando el documento. ¿En qué te ayudo?', isMine: false },
])

const newMessage = ref('')

const CREATE_CONV_MUTATION = gql`
  mutation CreateConversation($docId: ID!) {
    createConversation(document_id: $docId) {
      id
      title
      messages {
        id
        role
        content
      }
    }
  }
`

const SEND_MSG_MUTATION = gql`
  mutation SendMessage($convId: ID!, $content: String!) {
    sendMessage(conversation_id: $convId, content: $content) {
      id
      role
      content
      source_chunk_ids
    }
  }
`

const { mutate: createConv } = useMutation(CREATE_CONV_MUTATION)
const { mutate: sendMsg } = useMutation(SEND_MSG_MUTATION)

onMounted(async () => {
  try {
    const res = await createConv({ docId: docId.value })
    const conv = res.data.createConversation
    conversationId.value = conv.id
    if (conv.messages && conv.messages.length > 0) {
      messages.value = conv.messages.map(m => ({
        text: m.content,
        isMine: m.role === 'user',
        chunks: m.source_chunk_ids ? m.source_chunk_ids.map(id => ({ id })) : null
      }))
    }
  } catch (e) {
    console.error("Error creating conversation", e)
  }
})

const sendMessage = async () => {
  if (!newMessage.value.trim() || !conversationId.value) return
  
  const userMsg = newMessage.value
  messages.value.push({ text: userMsg, isMine: true })
  newMessage.value = ''
  
  try {
    const res = await sendMsg({ convId: conversationId.value, content: userMsg })
    const aiMsg = res.data.sendMessage
    
    messages.value.push({
      text: aiMsg.content,
      isMine: false,
      chunks: aiMsg.source_chunk_ids ? aiMsg.source_chunk_ids.map(id => ({ id })) : null
    })
  } catch (error) {
    messages.value.push({
      text: 'Error de red o servidor: ' + error.message,
      isMine: false
    })
  }
}

const goBack = () => {
  router.push({ name: 'Dashboard' })
}
</script>

<style scoped>
.docu-chat-container {
  height: 100vh;
  overflow: hidden;
}
.border-b {
  border-bottom: 1px solid #263140;
}
.border-t {
  border-top: 1px solid #263140;
}
.message-bubble {
  max-width: 75%;
  line-height: 1.5;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.rounded-bubble-mine {
  border-radius: 20px 20px 4px 20px;
}
.rounded-bubble-other {
  border-radius: 20px 20px 20px 4px;
}
.chat-messages-area {
  scroll-behavior: smooth;
}
</style>
