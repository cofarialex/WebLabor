import { useState } from "react";

export default function SearchForm({ onSearch, loading }) {
  const [city, setCity] = useState("");

  function handleSubmit(e) {
    e.preventDefault();
    if (city.trim()) onSearch(city.trim());
  }

  return (
    <form className="search" onSubmit={handleSubmit}>
      <input
        value={city}
        onChange={(e) => setCity(e.target.value)}
        placeholder="Város, pl. Budapest"
      />
      <button type="submit" disabled={loading}>
        {loading ? "Betöltés..." : "Lekérés"}
      </button>
    </form>
  );
}