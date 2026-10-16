import { useState } from "react";
import SearchForm from "./components/SearchForm";
import WeatherCard from "./components/WeatherCard";
import { getWeather } from "./services/api";
import "./style.css";

function App() {
  const [weather, setWeather] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleSearch = async (city) => {
    setLoading(true);
    setError("");
    setWeather(null);
    try {
      const data = await getWeather(city);
      setWeather(data);
    } catch (err) {
      setError(err.response?.data?.error || err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="app-container" style={{ textAlign: 'center', marginTop: '50px' }}>
      <h1>Időjárás App</h1>
      <SearchForm onSearch={handleSearch} loading={loading} />
      {error && <p style={{ color: "red", marginTop: '10px' }}>{error}</p>}
      {weather && <WeatherCard data={weather} />}
    </div>
  );
}

export default App;