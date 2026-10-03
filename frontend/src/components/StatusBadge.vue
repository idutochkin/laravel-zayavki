<script setup>
// Цветная плашка статуса. Текст приходит с сервера (status_label), цвет выбираем по коду.
defineProps({
  status: { type: String, required: true },
  label: { type: String, required: true },
})
</script>

<template>
  <span class="badge" :class="`badge-${status}`">{{ label }}</span>
</template>
