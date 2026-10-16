const { onRequest } = require("firebase-functions/v2/https");
const axios = require("axios");

// Open-Meteo: ingyenes időjárás API, nem kell hozzá regisztráció és API kulcs
const GEO_URL = "https://geocoding-api.open-meteo.com/v1/search";
const WEATHER_URL = "https://api.open-meteo.com/v1/forecast";

// Az Open-Meteo számkódot ad az időjárásra (WMO kód) -> ezt fordítjuk szövegre és ikonra
function describe(code) {
  if (code === 0) return { description: "derült ég", icon: "☀️" };
  if (code <= 2) return { description: "enyhén felhős", icon: "🌤️" };
  if (code === 3) return { description: "borult ég", icon: "☁️" };
  if (code <= 48) return { description: "köd", icon: "🌫️" };
  if (code <= 57) return { description: "szitálás", icon: "🌦️" };
  if (code <= 67) return { description: "eső", icon: "🌧️" };
  if (code <= 77) return { description: "havazás", icon: "🌨️" };
  if (code <= 82) return { description: "zápor", icon: "🌧️" };
  if (code <= 86) return { description: "hózápor", icon: "🌨️" };
  return { description: "zivatar", icon: "⛈️" };
}

exports.getWeather = onRequest({ cors: true, maxInstances: 10 }, async (req, res) => {
  const city = req.query.city;

  if (!city) {
    return res.status(400).json({ error: "Add meg a várost!" });
  }

  try {
    // 1. lépés: a város nevéből koordinátákat kérünk
    const geo = await axios.get(GEO_URL, {
      params: { name: city, count: 1, language: "hu" },
    });
    const place = geo.data.results?.[0];
    if (!place) {
      return res.status(404).json({ error: `Nem található ilyen város: ${city}` });
    }

    // 2. lépés: a koordinátákra lekérjük az aktuális időjárást
    const weather = await axios.get(WEATHER_URL, {
      params: {
        latitude: place.latitude,
        longitude: place.longitude,
        current: "temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m",
        wind_speed_unit: "ms",
      },
    });
    const current = weather.data.current;

    // 3. lépés: egyszerű, saját formátumban küldjük vissza a frontendnek
    res.json({
      name: place.name,
      country: place.country_code,
      temp: current.temperature_2m,
      feelsLike: current.apparent_temperature,
      humidity: current.relative_humidity_2m,
      wind: current.wind_speed_10m,
      ...describe(current.weather_code),
    });
  } catch (error) {
    const status = error.response?.status || 500;
    const message = error.response?.data?.reason || error.message;
    res.status(status).json({ error: message });
  }
});
