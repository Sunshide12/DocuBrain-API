<template>
  <v-container fluid class="fill-height bg-background d-flex align-center justify-center">
    <v-card width="100%" max-width="400" class="bg-surface pa-6 rounded-lg doc-card" elevation="2">
      <v-card-title class="text-h5 font-weight-bold text-center text-on-surface mb-2">
        DocuBrain
      </v-card-title>
      <v-card-subtitle class="text-center text-on-surface-variant mb-6">
        {{ isRegister ? 'Crea una cuenta' : 'Inicia sesión para continuar' }}
      </v-card-subtitle>
      
      <v-card-text>
        <v-form @submit.prevent="handleSubmit">
          <v-text-field
            v-if="isRegister"
            v-model="name"
            label="Nombre"
            variant="solo-filled"
            bg-color="surface-bright"
            hide-details="auto"
            class="mb-4"
            required
          ></v-text-field>

          <v-text-field
            v-model="email"
            label="Email"
            variant="solo-filled"
            bg-color="surface-bright"
            hide-details="auto"
            class="mb-4"
            required
          ></v-text-field>
          
          <v-text-field
            v-model="password"
            label="Contraseña"
            type="password"
            variant="solo-filled"
            bg-color="surface-bright"
            hide-details="auto"
            class="mb-4"
            required
          ></v-text-field>

          <v-text-field
            v-if="isRegister"
            v-model="password_confirmation"
            label="Confirmar Contraseña"
            type="password"
            variant="solo-filled"
            bg-color="surface-bright"
            hide-details="auto"
            class="mb-6"
            required
          ></v-text-field>
          
          <v-btn
            color="primary"
            elevation="2"
            rounded="lg"
            class="docu-btn-smooth mb-4"
            block
            type="submit"
            :loading="loginLoading || registerLoading"
          >
            {{ isRegister ? 'Registrarse' : 'Entrar' }}
          </v-btn>

          <div class="text-center">
            <v-btn variant="text" size="small" @click="isRegister = !isRegister" class="text-on-surface-variant">
              {{ isRegister ? '¿Ya tienes cuenta? Inicia sesión' : '¿No tienes cuenta? Regístrate' }}
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@vue/apollo-composable'
import gql from 'graphql-tag'

const LOGIN_MUTATION = gql`
  mutation Login($email: String!, $password: String!) {
    login(input: { email: $email, password: $password }) {
      token
      user {
        id
        name
      }
    }
  }
`

const REGISTER_MUTATION = gql`
  mutation Register($name: String!, $email: String!, $password: String!, $password_confirmation: String!) {
    register(input: { 
      name: $name, 
      email: $email, 
      password: $password, 
      password_confirmation: $password_confirmation 
    }) {
      token
      user {
        id
        name
      }
    }
  }
`

const router = useRouter()
const isRegister = ref(false)
const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')

const { mutate: login, loading: loginLoading } = useMutation(LOGIN_MUTATION)
const { mutate: register, loading: registerLoading } = useMutation(REGISTER_MUTATION)

const handleSuccess = (token) => {
  localStorage.setItem('token', token)
  router.push({ name: 'Dashboard' })
}

const handleSubmit = async () => {
  try {
    if (isRegister.value) {
      if (password.value !== password_confirmation.value) {
        alert('Las contraseñas no coinciden')
        return
      }
      const res = await register({
        name: name.value,
        email: email.value,
        password: password.value,
        password_confirmation: password_confirmation.value
      })
      handleSuccess(res.data.register.token)
    } else {
      const res = await login({
        email: email.value,
        password: password.value
      })
      handleSuccess(res.data.login.token)
    }
  } catch (error) {
    alert('Error: ' + error.message)
  }
}
</script>

<style scoped>
.doc-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.docu-btn-smooth {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.docu-btn-smooth:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2) !important;
}
.docu-btn-smooth:active {
  transform: translateY(1px);
}
</style>
