<template>
  <div class="app-container">
    <h1>Időjárás App</h1>

    <div class="search-box">
      <!-- Input mező, ami a "varos" változóhoz van kötve -->
      <input
        type="text"
        v-model="varos"
        placeholder="Írd be a város nevét..."
      />
      <!-- Keresés gomb, ami a "kereses" függvényt indítja el -->
      <button @click="kereses">Keresés</button>
    </div>

    <!-- Időjárás kártya, ami csak akkor jelenik meg, ha már rányomtunk a keresésre -->
    <div v-if="keresve" class="weather-card">
      <h2>{{ varos }}</h2>
      <p>Itt fognak megjelenni az adatok...</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

// Változók létrehozása (a ref() jelzi a Vue-nak, hogy figyelje a változásaikat)
const varos = ref('')
const keresve = ref(false)

// Ez a függvény fut le, amikor a gombra kattintasz
const kereses = () => {
  if (varos.value !== '') {
    keresve.value = true // Megjelenítjük a kártyát
    console.log('Keresett város:', varos.value)
    // Később ide jön az Axios API hívás!
  }
}
</script>

<style scoped>
/* Egy kis alap stílus, hogy jobban nézzen ki */
.app-container {
  text-align: center;
  font-family: Arial, sans-serif;
  margin-top: 50px;
}
.search-box {
  margin-bottom: 20px;
}
input {
  padding: 10px;
  font-size: 16px;
  margin-right: 10px;
  border-radius: 4px;
  border: 1px solid #ccc;
}
button {
  padding: 10px 20px;
  font-size: 16px;
  cursor: pointer;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
}
button:hover {
  background-color: #45a049;
}
.weather-card {
  border: 1px solid #ddd;
  padding: 20px;
  max-width: 300px;
  margin: 0 auto;
  border-radius: 8px;
  background-color: #f9f9f9;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  color: black;
}
</style>