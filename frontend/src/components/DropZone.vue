<template>
  <div v-bind="getRootProps()" class="upload-zone bg-surface pa-10" :class="{ 'is-drag-active': isDragActive }">
    <input v-bind="getInputProps()" />
    <v-icon size="64" color="primary" class="mb-4">mdi-cloud-upload-outline</v-icon>
    <h3 class="text-h6 text-on-surface mb-2">Arrastra tu PDF aquí</h3>
    <p class="text-on-surface-variant text-body-2 mb-6">o haz clic para explorar en tu equipo</p>
    <v-btn color="primary" rounded="lg" class="px-8 text-none docu-btn-smooth">Seleccionar Archivo</v-btn>
  </div>
</template>

<script setup>
import { useDropzone } from 'vue3-dropzone'

const emit = defineEmits(['file-dropped'])

const onDrop = (acceptedFiles) => {
  if (acceptedFiles.length > 0) {
    emit('file-dropped', acceptedFiles[0])
  }
}

const { getRootProps, getInputProps, isDragActive } = useDropzone({
  onDrop,
  accept: 'application/pdf',
  multiple: false
})
</script>

<style scoped>
.upload-zone {
  border: 2px dashed #263140; /* surface-light */
  transition: border-color 0.3s ease, background-color 0.3s ease;
  border-radius: 16px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.upload-zone:hover, .is-drag-active {
  border-color: #0EA5E9; /* primary */
  background-color: rgba(14, 165, 233, 0.05) !important;
}
.docu-btn-smooth {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  font-weight: 600;
  letter-spacing: 0.3px;
}
</style>
