import axios from "axios";

// Fejlesztéskor a Vite proxy, élesben a Firebase Hosting rewrite irányítja a kérést a Functionre
export async function getWeather(city) {
  const { data } = await axios.get("/api/weather", { params: { city } });
  return data;
}
